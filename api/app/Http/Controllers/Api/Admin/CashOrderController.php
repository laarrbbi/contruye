<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminOrderResource;
use App\Mail\PaymentConfirmedMail;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Cash paid at the office. The README always documented this flow, but no
 * code path could create a `cash` order: the admin could only grant an order
 * the student had placed online, which then read as a transfer or card in the
 * revenue by method.
 *
 * Priced by the same PricingService as checkout (tiers, campaigns, owned
 * modules excluded, best price wins), never typed in by hand. `confirm:
 * false` returns the quote to show the admin; `confirm: true` records the
 * paid order through Order::markPaidAndEnroll(), the only path to an
 * enrollment.
 */
class CashOrderController extends Controller
{
    public function __construct(private readonly PricingService $pricing) {}

    public function store(Request $request, User $user): JsonResponse
    {
        abort_unless($user->role === 'student', 404);

        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'scope' => ['required', Rule::in(['full', 'modules'])],
            'module_ids' => ['required_if:scope,modules', 'array', 'min:1'],
            'module_ids.*' => ['integer', 'distinct', 'exists:modules,id'],
            'confirm' => ['required', 'boolean'],
        ]);

        $course = Course::query()->findOrFail($data['course_id']);

        $quote = $this->pricing->quote(
            $course,
            $user,
            $data['scope'],
            array_map('intval', $data['module_ids'] ?? []),
            null,
        );

        if (! $request->boolean('confirm')) {
            return response()->json(['data' => ['quote' => $quote]]);
        }

        $order = DB::transaction(function () use ($request, $user, $course, $quote) {
            $order = Order::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'amount' => $quote['total'],
                'discount_percent' => $quote['tier_percent'],
                'pricing_snapshot' => PricingService::snapshot($quote),
                'currency' => PricingService::CURRENCY,
                'method' => 'cash',
                'scope' => $quote['scope'],
                'status' => 'pending',
            ]);

            foreach ($quote['items'] as $item) {
                $order->orderModules()->create([
                    'module_id' => $item['module_id'],
                    'unit_price' => $item['unit_price'],
                ]);
            }

            $order->markPaidAndEnroll();

            AuditLog::record($request->user(), 'order.cash_recorded', $order, [
                'amount' => $order->amount,
                'scope' => $order->scope,
            ]);

            Mail::to($user)->queue(new PaymentConfirmedMail($order));

            return $order;
        });

        return AdminOrderResource::make($order->fresh()->load(['user', 'course', 'orderModules.module']))
            ->response()
            ->setStatusCode(201);
    }
}
