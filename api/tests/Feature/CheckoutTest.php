<?php

use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use App\Services\StripeService;
use Stripe\Exception\ApiConnectionException;

it('requires authentication', function () {
    $this->postJson('/api/checkout', ['course_id' => 1, 'method' => 'stripe'])
        ->assertUnauthorized();
});

it('validates the payload', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/checkout', ['course_id' => 999999, 'method' => 'paypal'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['course_id', 'method']);
});

it('rejects checkout for an unpublished course', function () {
    $user = User::factory()->create();
    $course = Course::factory()->draft()->create();

    $this->actingAs($user)
        ->postJson('/api/checkout', ['course_id' => $course->id, 'method' => 'manual_transfer'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['course_id']);

    $this->assertDatabaseCount('orders', 0);
});

it('rejects checkout when the user is already enrolled', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create();
    Enrollment::factory()->for($user)->for($course)->create();

    $this->actingAs($user)
        ->postJson('/api/checkout', ['course_id' => $course->id, 'method' => 'stripe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['course_id']);

    $this->assertDatabaseCount('orders', 0);
});

it('creates a stripe checkout session with the EUR amount', function () {
    app()->instance(StripeService::class, new class extends StripeService
    {
        public function __construct() {}

        public function createCheckoutSession(
            Order $order,
            Course $course,
            User $user,
            string $successUrl,
            string $cancelUrl,
        ): array {
            // The buyer comes back to the order it paid for, either way.
            expect($successUrl)->toContain("/checkout/success?order={$order->id}")
                ->and($cancelUrl)->toContain("/checkout/failure?order={$order->id}");

            return ['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/test'];
        }
    });

    $user = User::factory()->create();
    $course = Course::factory()->create(['price_eur' => 490]);

    $response = $this->actingAs($user)
        ->postJson('/api/checkout', ['course_id' => $course->id, 'method' => 'stripe'])
        ->assertCreated()
        ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/test')
        ->assertJsonPath('data.order.status', 'pending')
        ->assertJsonPath('data.order.method', 'stripe')
        ->assertJsonPath('data.order.currency', 'EUR')
        ->assertJsonPath('data.order.amount', 490);

    $this->assertDatabaseHas('orders', [
        'id' => $response->json('data.order.id'),
        'currency' => 'EUR',
        'amount' => 490,
        'status' => 'pending',
        'stripe_session_id' => 'cs_test_1',
    ]);
});

it('returns payment instructions for manual methods without a checkout url', function () {
    config([
        'services.manual_payment.iban' => 'ES91 2100 0418 4502 0005 1332',
        'services.manual_payment.holder' => '[DEMO] Construye Academy S.L.',
        'services.manual_payment.bizum_phone' => '612345678',
    ]);

    $user = User::factory()->create();
    $course = Course::factory()->create(['price_eur' => 300]);

    $response = $this->actingAs($user)
        ->postJson('/api/checkout', ['course_id' => $course->id, 'method' => 'manual_bizum'])
        ->assertCreated()
        ->assertJsonPath('data.order.status', 'pending')
        ->assertJsonPath('data.payment_instructions.method', 'manual_bizum')
        ->assertJsonPath('data.payment_instructions.amount', 300)
        ->assertJsonPath('data.payment_instructions.currency', 'EUR')
        ->assertJsonPath('data.payment_instructions.iban', 'ES91 2100 0418 4502 0005 1332')
        ->assertJsonPath('data.payment_instructions.holder', '[DEMO] Construye Academy S.L.')
        ->assertJsonPath('data.payment_instructions.bizum_phone', '612345678');

    // The reference the buyer writes on the transfer is what matches it to the order.
    expect($response->json('data.payment_instructions.reference'))->toBe('CONSTRUYE-'.$response->json('data.order.id'))
        ->and($response->json('data'))->not->toHaveKey('checkout_url');
});

it('applies a redeemable coupon to the amount', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create(['price_eur' => 490]);
    $coupon = Coupon::factory()->create(['percent_off' => 20]);

    $response = $this->actingAs($user)
        ->postJson('/api/checkout', [
            'course_id' => $course->id,
            'method' => 'manual_transfer',
            'coupon_code' => $coupon->code,
        ])
        ->assertCreated()
        ->assertJsonPath('data.order.amount', 392);

    $this->assertDatabaseHas('orders', [
        'id' => $response->json('data.order.id'),
        'amount' => 392,
        'coupon_id' => $coupon->id,
    ]);
});

it('rejects an expired coupon', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create();
    $coupon = Coupon::factory()->create(['expires_at' => now()->subDay()]);

    $this->actingAs($user)
        ->postJson('/api/checkout', [
            'course_id' => $course->id,
            'method' => 'manual_transfer',
            'coupon_code' => $coupon->code,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['coupon_code']);

    $this->assertDatabaseCount('orders', 0);
});

it('rejects a coupon that reached its max uses', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create();
    $coupon = Coupon::factory()->create(['max_uses' => 5, 'uses' => 5]);

    $this->actingAs($user)
        ->postJson('/api/checkout', [
            'course_id' => $course->id,
            'method' => 'manual_transfer',
            'coupon_code' => $coupon->code,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['coupon_code']);
});

it('caps a limited coupon at the discount, not just at the counter', function () {
    $courseA = Course::factory()->create(['price_eur' => 100]);
    $courseB = Course::factory()->create(['price_eur' => 100]);
    $coupon = Coupon::factory()->create(['percent_off' => 50, 'max_uses' => 1, 'uses' => 0]);

    // The use is claimed when the discounted price is committed, so the
    // second buyer is refused at checkout — not silently given the discount
    // while the counter stays at 1.
    $orderAId = $this->actingAs(User::factory()->create())->postJson('/api/checkout', [
        'course_id' => $courseA->id, 'method' => 'manual_transfer', 'coupon_code' => $coupon->code,
    ])->assertCreated()->json('data.order.id');

    expect(Order::find($orderAId)->amount)->toBe(50)
        ->and($coupon->fresh()->uses)->toBe(1);

    $this->actingAs(User::factory()->create())->postJson('/api/checkout', [
        'course_id' => $courseB->id, 'method' => 'manual_transfer', 'coupon_code' => $coupon->code,
    ])->assertUnprocessable()->assertJsonValidationErrors(['coupon_code']);

    // No second discounted order exists at all.
    expect(Order::query()->where('coupon_id', $coupon->id)->count())->toBe(1)
        ->and($coupon->fresh()->uses)->toBe(1);

    // Paying the first order does not double-count it.
    Order::find($orderAId)->markPaidAndEnroll();
    expect($coupon->fresh()->uses)->toBe(1);
});

it('gives a reserved coupon use back when the order never completes', function () {
    $course = Course::factory()->create(['price_eur' => 100]);
    $coupon = Coupon::factory()->create(['percent_off' => 50, 'max_uses' => 1, 'uses' => 0]);

    $orderId = $this->actingAs(User::factory()->create())->postJson('/api/checkout', [
        'course_id' => $course->id, 'method' => 'manual_transfer', 'coupon_code' => $coupon->code,
    ])->assertCreated()->json('data.order.id');

    expect($coupon->fresh()->uses)->toBe(1);

    // The buyer's receipt is rejected: the coupon must become available again.
    Order::find($orderId)->markFailed();
    expect($coupon->fresh()->uses)->toBe(0);

    // And it can now be redeemed by someone else.
    $this->actingAs(User::factory()->create())->postJson('/api/checkout', [
        'course_id' => $course->id, 'method' => 'manual_transfer', 'coupon_code' => $coupon->code,
    ])->assertCreated();

    expect($coupon->fresh()->uses)->toBe(1);
});

it('rejects an unknown coupon code', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/checkout', [
            'course_id' => $course->id,
            'method' => 'manual_transfer',
            'coupon_code' => 'NOPE',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['coupon_code']);
});

it('marks the order failed and returns 502 when stripe cannot open a session', function () {
    app()->instance(StripeService::class, new class extends StripeService
    {
        public function __construct() {}

        public function createCheckoutSession(
            Order $order,
            Course $course,
            User $user,
            string $successUrl,
            string $cancelUrl,
        ): array {
            throw new RuntimeException('Stripe API is unreachable.');
        }
    });

    $user = User::factory()->create();
    $course = Course::factory()->create(['price_eur' => 490]);
    $coupon = Coupon::factory()->create(['percent_off' => 50, 'max_uses' => 1, 'uses' => 0]);

    // A provider outage is a 502 and a failed order, never a 500 that
    // strands the buyer on a pending one with no checkout link.
    $this->actingAs($user)->postJson('/api/checkout', [
        'course_id' => $course->id,
        'method' => 'stripe',
        'coupon_code' => $coupon->code,
    ])->assertStatus(502)->assertJsonStructure(['message']);

    $order = Order::query()->where('user_id', $user->id)->latest('id')->first();

    expect($order)->not->toBeNull()
        ->and($order->status)->toBe('failed')
        ->and($order->currency)->toBe('EUR')
        ->and($order->stripe_session_id)->toBeNull()
        // ...and the coupon use reserved for the failed order is handed back,
        // so the provider's outage does not burn the buyer's discount.
        ->and($coupon->fresh()->uses)->toBe(0);
});

it('fails the order cleanly when the network itself fails on the way to Stripe', function () {
    // What a proxy refusing the tunnel looks like from the SDK: an exception
    // raised before any response is read.
    app()->instance(StripeService::class, new class extends StripeService
    {
        public function __construct() {}

        public function createCheckoutSession(
            Order $order,
            Course $course,
            User $user,
            string $successUrl,
            string $cancelUrl,
        ): array {
            throw new ApiConnectionException('Could not connect to Stripe.');
        }
    });

    $user = User::factory()->create();
    $course = Course::factory()->create(['price_eur' => 490]);

    $this->actingAs($user)
        ->postJson('/api/checkout', ['course_id' => $course->id, 'method' => 'stripe'])
        ->assertStatus(502)
        ->assertJsonPath('message', __('The payment provider is unavailable. Please try again later.'));

    expect(Order::query()->sole()->status)->toBe('failed');
});
