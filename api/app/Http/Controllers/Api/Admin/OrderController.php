<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RejectOrderRequest;
use App\Http\Resources\AdminOrderResource;
use App\Mail\PaymentConfirmedMail;
use App\Mail\ReceiptRejectedMail;
use App\Models\AuditLog;
use App\Models\Order;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = $this->perPage($request);

        return AdminOrderResource::collection($this->filteredQuery($request)->paginate($perPage));
    }

    /**
     * Stream the (filtered) orders as a CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filteredQuery($request);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens accents/Arabic correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                __('ID'),
                __('Date'),
                __('Customer'),
                __('Email'),
                __('Phone'),
                __('Course'),
                __('Amount'),
                __('Currency'),
                __('Method'),
                __('Status'),
                __('Paid at'),
                __('Admin note'),
            ]);

            $query->lazy(200)->each(function (Order $order) use ($out) {
                fputcsv($out, [
                    $order->id,
                    $order->created_at?->format('Y-m-d H:i'),
                    $order->user?->name,
                    $order->user?->email,
                    $order->user?->phone,
                    $order->course?->localizedTitle(),
                    $order->amount,
                    $order->currency,
                    $order->method,
                    $order->status,
                    $order->paid_at?->format('Y-m-d H:i'),
                    $order->admin_note,
                ]);
            });

            fclose($out);
        }, 'commandes-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function filteredQuery(Request $request)
    {
        $query = Order::query()->with(['user', 'course']);

        $status = $request->query('status');

        if ($status) {
            $query->where('status', $status);
        }

        // Receipts to review are a queue: the buyer who has waited longest
        // comes first. Every other view reads newest first.
        if ($status === 'awaiting_review') {
            $query->orderBy('receipt_uploaded_at')->orderBy('id');
        } else {
            $query->latest()->latest('id');
        }

        if ($method = $request->query('method')) {
            $query->where('method', $method);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->whereHas('user', function ($user) use ($search) {
                $user->where(function ($q) use ($search) {
                    $q->where('name', 'like', Like::contains($search))
                        ->orWhere('email', 'like', Like::contains($search));
                });
            });
        }

        return $query;
    }

    public function show(Order $order): AdminOrderResource
    {
        return AdminOrderResource::make($order->load(['user', 'course', 'orderModules.module', 'invoice']));
    }

    /**
     * Stream the privately stored receipt file.
     */
    public function receipt(Order $order): StreamedResponse
    {
        $disk = Storage::disk('local');

        abort_if(
            $order->receipt_path === null || ! $disk->exists($order->receipt_path),
            404,
            __('No receipt has been uploaded for this order.'),
        );

        return $disk->response($order->receipt_path);
    }

    public function approve(Request $request, Order $order): AdminOrderResource|JsonResponse
    {
        if ($order->status !== 'awaiting_review') {
            return response()->json([
                'message' => __('Only orders awaiting review can be approved.'),
            ], 422);
        }

        // The transition, its audit entry and the queued mail commit together:
        // a failed queue write used to leave the order paid with no mail, and
        // a second click was refused because the order was no longer pending.
        DB::transaction(function () use ($request, $order): void {
            if ($order->markPaidAndEnroll()) {
                AuditLog::record($request->user(), 'order.approved', $order);
                Mail::to($order->user)->queue(new PaymentConfirmedMail($order));
            }
        });

        return AdminOrderResource::make($order->load(['user', 'course']));
    }

    public function reject(RejectOrderRequest $request, Order $order): AdminOrderResource|JsonResponse
    {
        if ($order->status !== 'awaiting_review') {
            return response()->json([
                'message' => __('Only orders awaiting review can be rejected.'),
            ], 422);
        }

        $reason = $request->validated('reason');

        $order->update(['admin_note' => $reason]);
        // Releases the coupon use the order was holding.
        $order->markFailed();

        AuditLog::record($request->user(), 'order.rejected', $order, ['reason' => $reason]);
        Mail::to($order->user)->queue(new ReceiptRejectedMail($order, $reason));

        return AdminOrderResource::make($order->load(['user', 'course']));
    }

    /**
     * Manually grant the enrollment (e.g. cash payment at the office).
     */
    public function grant(Request $request, Order $order): AdminOrderResource|JsonResponse
    {
        if ($order->status === 'paid') {
            return response()->json([
                'message' => __('This order has already been paid.'),
            ], 422);
        }

        // The transition, its audit entry and the queued mail commit together:
        // a failed queue write used to leave the order paid with no mail, and
        // a second click was refused because the order was no longer pending.
        DB::transaction(function () use ($request, $order): void {
            if ($order->markPaidAndEnroll()) {
                AuditLog::record($request->user(), 'order.granted', $order);
                Mail::to($order->user)->queue(new PaymentConfirmedMail($order));
            }
        });

        return AdminOrderResource::make($order->load(['user', 'course']));
    }
}
