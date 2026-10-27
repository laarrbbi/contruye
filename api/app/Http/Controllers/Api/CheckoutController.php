<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\QuoteRequest;
use App\Http\Resources\OrderResource;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use App\Services\PaymentMethods;
use App\Services\PricingService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(private readonly PricingService $pricing) {}

    /**
     * The checkout methods that can take money right now, in display order.
     * The checkout page offers these and nothing else.
     */
    public function methods(): JsonResponse
    {
        return response()
            ->json(['data' => PaymentMethods::available()])
            ->header('Cache-Control', 'no-cache');
    }

    /**
     * Live pricing quote for the checkout/landing UI. Guest-friendly:
     * an authenticated user additionally gets already-owned modules
     * excluded from the selection.
     */
    public function quote(QuoteRequest $request): JsonResponse
    {
        $course = Course::query()->findOrFail($request->validated('course_id'));

        abort_unless($course->status === 'published', 404);

        $quote = $this->pricing->quote(
            $course,
            $request->user('sanctum'),
            $request->scopeValue(),
            $request->moduleIds(),
            $this->resolveCoupon($request->validated('coupon_code')),
        );

        return response()->json(['data' => $quote]);
    }

    public function store(CheckoutRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        /** @var Course $course */
        $course = Course::query()->findOrFail($data['course_id']);

        if ($course->status !== 'published') {
            throw ValidationException::withMessages([
                'course_id' => [__('This course is not available for purchase.')],
            ]);
        }

        $method = $data['method'];
        $currency = PricingService::CURRENCY;
        $coupon = $this->resolveCoupon($data['coupon_code'] ?? null, failWhenInvalid: true);

        // The quote is the single source of truth: it validates ownership,
        // module membership and computes every discount.
        $quote = $this->pricing->quote(
            $course,
            $user,
            $request->scopeValue(),
            $request->moduleIds(),
            $coupon,
        );

        $order = DB::transaction(function () use ($user, $course, $coupon, $quote, $method, $currency) {
            // Claim the coupon use now, while the discounted price is being
            // committed. Counting it at payment instead let every pending
            // order hold the same capped discount.
            if ($coupon !== null && ! $coupon->reserveUse()) {
                throw ValidationException::withMessages([
                    'coupon_code' => [__('This coupon code is invalid or has expired.')],
                ]);
            }

            $order = Order::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'coupon_id' => $coupon?->id,
                'amount' => $quote['total'],
                'discount_percent' => $quote['tier_percent'],
                // Every term that produced this price, kept with the order:
                // coupons and campaigns can be edited or deleted afterwards,
                // and a paid order must still be able to explain itself.
                'pricing_snapshot' => PricingService::snapshot($quote),
                'currency' => $currency,
                'method' => $method,
                'scope' => $quote['scope'],
                'status' => 'pending',
            ]);

            foreach ($quote['items'] as $item) {
                $order->orderModules()->create([
                    'module_id' => $item['module_id'],
                    'unit_price' => $item['unit_price'],
                ]);
            }

            return $order;
        });

        return match ($method) {
            'stripe' => $this->stripeCheckout($order, $course, $user),
            default => $this->manualCheckout($order),
        };
    }

    private function resolveCoupon(?string $code, bool $failWhenInvalid = false): ?Coupon
    {
        if (empty($code)) {
            return null;
        }

        $coupon = Coupon::query()->where('code', $code)->first();

        if ($coupon === null || ! $coupon->isRedeemable()) {
            if ($failWhenInvalid) {
                throw ValidationException::withMessages([
                    'coupon_code' => [__('This coupon code is invalid or has expired.')],
                ]);
            }

            return null;
        }

        return $coupon;
    }

    private function stripeCheckout(Order $order, Course $course, User $user): JsonResponse
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        try {
            $session = $this->stripeService()->createCheckoutSession(
                $order,
                $course,
                $user,
                "{$frontend}/checkout/success?order={$order->id}",
                "{$frontend}/checkout/failure?order={$order->id}",
            );
        } catch (Throwable $e) {
            Log::error('Stripe checkout session creation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            $order->markFailed();

            return response()->json([
                'message' => __('The payment provider is unavailable. Please try again later.'),
            ], 502);
        }

        $order->update(['stripe_session_id' => $session['id']]);

        return response()->json([
            'data' => [
                'order' => OrderResource::make($order->load(['course', 'orderModules.module'])),
                'checkout_url' => $session['url'],
            ],
        ], 201);
    }

    private function manualCheckout(Order $order): JsonResponse
    {
        return response()->json([
            'data' => [
                'order' => OrderResource::make($order->load(['course', 'orderModules.module'])),
                'payment_instructions' => OrderResource::paymentInstructions($order),
            ],
        ], 201);
    }

    /**
     * Resolve from the container first so tests can bind a fake; fall back to
     * fromConfig() because the service has scalar constructor dependencies
     * the container cannot auto-wire.
     */
    private function stripeService(): StripeService
    {
        return app()->bound(StripeService::class)
            ? app(StripeService::class)
            : StripeService::fromConfig();
    }
}
