<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoicePayloadRequest;
use App\Http\Resources\AdminInvoiceResource;
use App\Mail\InvoiceIssuedMail;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceBuilder;
use App\Services\InvoicePdf;
use App\Support\Like;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = $this->perPage($request);

        $query = Invoice::query()
            ->with(['order.user', 'order.course'])
            ->orderByDesc('year')
            ->orderByDesc('sequence');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', Like::contains($search))
                    ->orWhere('buyer_name', 'like', Like::contains($search))
                    ->orWhereHas('order.user', fn ($user) => $user
                        ->where('name', 'like', Like::contains($search))
                        ->orWhere('email', 'like', Like::contains($search)));
            });
        }

        return AdminInvoiceResource::collection($query->paginate($perPage));
    }

    public function store(InvoicePayloadRequest $request, Order $order): AdminInvoiceResource|JsonResponse
    {
        if ($order->status !== 'paid') {
            return response()->json([
                'message' => __('Only paid orders can be invoiced.'),
            ], 422);
        }

        $settings = Invoice::sellerSettings();

        if (! $settings['complete']) {
            return response()->json([
                'message' => __('Fill in the seller information before issuing invoices.'),
                'missing' => $settings['missing'],
            ], 422);
        }

        if ($order->scope === 'modules' && $order->orderModules()->doesntExist()) {
            // A catalogue module was deleted and cascaded away the pivot
            // rows: the order's lines are gone, and billing it as "full
            // access" would fabricate a document.
            return response()->json([
                'message' => __('The line items of this order are no longer available; this order cannot be invoiced automatically.'),
            ], 422);
        }

        $data = $request->validated();
        $computed = (new InvoiceBuilder)->build($order, (float) $data['iva_rate']);

        $invoice = $this->retryOnSequenceRace(fn () => DB::transaction(function () use ($order, $data, $computed, $settings, $request) {
            // C20: two admins issuing for the same order serialize on its row.
            // The second finds the first one's invoice here, instead of
            // colliding on the unique order_id three times and answering 500.
            Order::query()->lockForUpdate()->findOrFail($order->id);

            if (Invoice::query()->where('order_id', $order->id)->exists()) {
                throw new HttpResponseException(response()->json([
                    'message' => __('An invoice already exists for this order.'),
                ], 422));
            }

            // A factura issued at 00:30 in Madrid must carry that day's date
            // and that year's sequence — in UTC it would still read as
            // yesterday, and on 1 January as last year.
            $issuedAt = $data['issued_at']
                ?? now()->setTimezone(config('app.business_timezone'))->toDateString();
            $year = (int) substr($issuedAt, 0, 4);

            // Locks the year's rows: the date check below reads a settled set.
            $number = Invoice::allocateNumber($year);

            // C15: numbers are chronological. Backdating stays possible (a
            // January invoice dated December) but never before an invoice
            // already issued: FAC-…-00012 cannot predate FAC-…-00011.
            $latest = Invoice::query()->where('year', $year)->max('issued_at');

            if ($latest !== null && $issuedAt < substr((string) $latest, 0, 10)) {
                throw ValidationException::withMessages([
                    'issued_at' => [__('An invoice dated :date has already been issued: a later number cannot carry an earlier date.', [
                        'date' => substr((string) $latest, 0, 10),
                    ])],
                ]);
            }

            return Invoice::create([
                'order_id' => $order->id,
                ...$number,
                'issued_at' => $issuedAt,
                'buyer_name' => $data['buyer_name'],
                'buyer_address' => $data['buyer_address'],
                'buyer_nif' => $data['buyer_nif'] ?? null,
                'seller' => $settings['seller'],
                'lines' => $computed['lines'],
                'iva_rate' => $computed['iva_rate'],
                'subtotal_net_c' => $computed['subtotal_net_c'],
                'discount_net_c' => $computed['discount_net_c'],
                'total_net_c' => $computed['total_net_c'],
                'iva_c' => $computed['iva_c'],
                'total_gross_c' => $computed['total_gross_c'],
                'currency' => $order->currency,
                'payment_method_label' => InvoiceBuilder::paymentMethodLabel($order->method),
                'payment_date' => $order->paid_at?->toDateString(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
        }));

        AuditLog::record($request->user(), 'invoice.created', $invoice, ['number' => $invoice->number]);

        return AdminInvoiceResource::make($invoice->load(['order.user', 'order.course']));
    }

    /**
     * The first invoice of a year has no row to lock, so two concurrent
     * issuances can both compute sequence 1 and collide on the unique
     * (year, sequence) index (or deadlock on the gap lock). Retrying restores
     * the gap-free sequence without surfacing a 500.
     *
     * @template T
     *
     * @param  callable(): T  $attempt
     * @return T
     */
    private function retryOnSequenceRace(callable $attempt)
    {
        $tries = 0;

        while (true) {
            try {
                return $attempt();
            } catch (QueryException $exception) {
                if (++$tries >= 3 || ! in_array($exception->getCode(), ['23000', '40001'], true)) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Correct an issued invoice (buyer typo, missing fiscal id, rate). The
     * number, issue date and line designations never change — the breakdown
     * is recomputed from the STORED lines, never from the live order, so
     * catalogue edits after issuance cannot rewrite the legal snapshot.
     */
    public function update(InvoicePayloadRequest $request, Invoice $invoice): AdminInvoiceResource
    {
        $data = $request->validated();

        $before = $invoice->only([
            'buyer_name', 'buyer_address', 'buyer_nif',
            'iva_rate', 'total_net_c', 'iva_c', 'notes',
        ]);

        $computed = (new InvoiceBuilder)->retotal(
            $invoice->lines,
            (float) $data['iva_rate'],
            $invoice->total_gross_c,
        );

        $invoice->update([
            'buyer_name' => $data['buyer_name'],
            'buyer_address' => $data['buyer_address'],
            'buyer_nif' => $data['buyer_nif'] ?? null,
            'lines' => $computed['lines'],
            'iva_rate' => $computed['iva_rate'],
            'subtotal_net_c' => $computed['subtotal_net_c'],
            'discount_net_c' => $computed['discount_net_c'],
            'total_net_c' => $computed['total_net_c'],
            'iva_c' => $computed['iva_c'],
            'total_gross_c' => $computed['total_gross_c'],
            'notes' => $data['notes'] ?? null,
        ]);

        // Corrections on a numbered fiscal document keep a before/after
        // trail so the original values stay recoverable.
        AuditLog::record($request->user(), 'invoice.updated', $invoice, [
            'number' => $invoice->number,
            'before' => $before,
            'after' => $invoice->only(array_keys($before)),
        ]);

        return AdminInvoiceResource::make($invoice->load(['order.user', 'order.course']));
    }

    public function pdf(Invoice $invoice): Response
    {
        return InvoicePdf::download($invoice);
    }

    /**
     * Email the invoice (PDF attached) to the buyer.
     */
    public function send(Request $request, Invoice $invoice): JsonResponse
    {
        $invoice->load('order.user');

        Mail::to($invoice->order->user)->queue(new InvoiceIssuedMail($invoice));

        AuditLog::record($request->user(), 'invoice.sent', $invoice, [
            'number' => $invoice->number,
            'to' => $invoice->order->user->email,
        ]);

        return response()->json(['message' => __('Invoice sent to :email.', [
            'email' => $invoice->order->user->email,
        ])]);
    }
}
