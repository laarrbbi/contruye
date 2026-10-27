<?php

use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use App\Services\StripeService;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

it('requires authentication', function () {
    $this->postJson('/api/checkout', ['course_id' => 1, 'method' => 'chargily'])
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
        ->postJson('/api/checkout', ['course_id' => $course->id, 'method' => 'manual_ccp'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['course_id']);

    $this->assertDatabaseCount('orders', 0);
});

it('rejects checkout when the user is already enrolled', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create();
    Enrollment::factory()->for($user)->for($course)->create();

    $this->actingAs($user)
        ->postJson('/api/checkout', ['course_id' => $course->id, 'method' => 'chargily'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['course_id']);

    $this->assertDatabaseCount('orders', 0);
});

it('creates a chargily checkout and stores the checkout id', function () {
    Http::fake([
        '*' => Http::response([
            'id' => '01hcheckout',
            'checkout_url' => 'https://pay.chargily.dz/checkout/01hcheckout',
        ]),
    ]);

    $user = User::factory()->create();
    $course = Course::factory()->create(['price_dzd' => 50000]);

    $response = $this->actingAs($user)
        ->postJson('/api/checkout', ['course_id' => $course->id, 'method' => 'chargily'])
        ->assertCreated()
        ->assertJsonPath('data.checkout_url', 'https://pay.chargily.dz/checkout/01hcheckout')
        ->assertJsonPath('data.order.status', 'pending')
        ->assertJsonPath('data.order.method', 'chargily')
        ->assertJsonPath('data.order.amount', 50000)
        ->assertJsonPath('data.order.currency', 'DZD');

    $orderId = $response->json('data.order.id');

    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'status' => 'pending',
        'chargily_checkout_id' => '01hcheckout',
    ]);

    Http::assertSent(function ($request) use ($orderId) {
        return str_contains($request->url(), '/checkouts')
            && $request['metadata']['order_id'] === $orderId
            && str_contains($request['webhook_endpoint'], '/api/webhooks/chargily')
            && str_contains($request['success_url'], "order={$orderId}");
    });
});

it('marks the order failed and returns 502 when chargily is down', function () {
    Http::fake(['*' => Http::response(['message' => 'boom'], 500)]);

    $user = User::factory()->create();
    $course = Course::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/checkout', ['course_id' => $course->id, 'method' => 'chargily'])
        ->assertStatus(502)
        ->assertJsonStructure(['message']);

    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'course_id' => $course->id,
        'status' => 'failed',
    ]);
});

it('rejects stripe checkout for a course without a EUR price', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create(['price_eur' => null]);

    $this->actingAs($user)
        ->postJson('/api/checkout', ['course_id' => $course->id, 'method' => 'stripe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['method']);

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
            return ['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/test'];
        }
    });

    $user = User::factory()->create();
    $course = Course::factory()->create(['price_dzd' => 50000, 'price_eur' => 199]);

    $response = $this->actingAs($user)
        ->postJson('/api/checkout', ['course_id' => $course->id, 'method' => 'stripe'])
        ->assertCreated()
        ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/test')
        ->assertJsonPath('data.order.currency', 'EUR')
        ->assertJsonPath('data.order.amount', 199);

    $this->assertDatabaseHas('orders', [
        'id' => $response->json('data.order.id'),
        'currency' => 'EUR',
        'amount' => 199,
        'status' => 'pending',
        'stripe_session_id' => 'cs_test_1',
    ]);
});

it('returns payment instructions for manual methods without a checkout url', function () {
    config([
        'services.manual_payment.ccp_number' => '1234567 89',
        'services.manual_payment.ccp_name' => 'EBNI ACADEMY SARL',
        'services.manual_payment.baridimob_rip' => '00799999000000000000',
    ]);

    $user = User::factory()->create();
    $course = Course::factory()->create(['price_dzd' => 30000]);

    $response = $this->actingAs($user)
        ->postJson('/api/checkout', ['course_id' => $course->id, 'method' => 'manual_baridimob'])
        ->assertCreated()
        ->assertJsonPath('data.order.status', 'pending')
        ->assertJsonPath('data.payment_instructions.method', 'manual_baridimob')
        ->assertJsonPath('data.payment_instructions.amount', 30000)
        ->assertJsonPath('data.payment_instructions.currency', 'DZD')
        ->assertJsonPath('data.payment_instructions.ccp_number', '1234567 89')
        ->assertJsonPath('data.payment_instructions.ccp_name', 'EBNI ACADEMY SARL')
        ->assertJsonPath('data.payment_instructions.baridimob_rip', '00799999000000000000');

    expect($response->json('data'))->not->toHaveKey('checkout_url');
});

it('applies a redeemable coupon to the amount', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create(['price_dzd' => 49900]);
    $coupon = Coupon::factory()->create(['percent_off' => 20]);

    $response = $this->actingAs($user)
        ->postJson('/api/checkout', [
            'course_id' => $course->id,
            'method' => 'manual_ccp',
            'coupon_code' => $coupon->code,
        ])
        ->assertCreated()
        ->assertJsonPath('data.order.amount', 39920);

    $this->assertDatabaseHas('orders', [
        'id' => $response->json('data.order.id'),
        'amount' => 39920,
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
            'method' => 'manual_ccp',
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
            'method' => 'manual_ccp',
            'coupon_code' => $coupon->code,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['coupon_code']);
});

it('caps a limited coupon at the discount, not just at the counter', function () {
    $courseA = Course::factory()->create(['price_dzd' => 10000]);
    $courseB = Course::factory()->create(['price_dzd' => 10000]);
    $coupon = Coupon::factory()->create(['percent_off' => 50, 'max_uses' => 1, 'uses' => 0]);

    // The use is claimed when the discounted price is committed, so the
    // second buyer is refused at checkout — not silently given the discount
    // while the counter stays at 1.
    $orderAId = $this->actingAs(User::factory()->create())->postJson('/api/checkout', [
        'course_id' => $courseA->id, 'method' => 'manual_ccp', 'coupon_code' => $coupon->code,
    ])->assertCreated()->json('data.order.id');

    expect(Order::find($orderAId)->amount)->toBe(5000)
        ->and($coupon->fresh()->uses)->toBe(1);

    $this->actingAs(User::factory()->create())->postJson('/api/checkout', [
        'course_id' => $courseB->id, 'method' => 'manual_ccp', 'coupon_code' => $coupon->code,
    ])->assertUnprocessable()->assertJsonValidationErrors(['coupon_code']);

    // No second discounted order exists at all.
    expect(Order::query()->where('coupon_id', $coupon->id)->count())->toBe(1)
        ->and($coupon->fresh()->uses)->toBe(1);

    // Paying the first order does not double-count it.
    Order::find($orderAId)->markPaidAndEnroll();
    expect($coupon->fresh()->uses)->toBe(1);
});

it('gives a reserved coupon use back when the order never completes', function () {
    $course = Course::factory()->create(['price_dzd' => 10000]);
    $coupon = Coupon::factory()->create(['percent_off' => 50, 'max_uses' => 1, 'uses' => 0]);

    $orderId = $this->actingAs(User::factory()->create())->postJson('/api/checkout', [
        'course_id' => $course->id, 'method' => 'manual_ccp', 'coupon_code' => $coupon->code,
    ])->assertCreated()->json('data.order.id');

    expect($coupon->fresh()->uses)->toBe(1);

    // The buyer's receipt is rejected: the coupon must become available again.
    Order::find($orderId)->markFailed();
    expect($coupon->fresh()->uses)->toBe(0);

    // And it can now be redeemed by someone else.
    $this->actingAs(User::factory()->create())->postJson('/api/checkout', [
        'course_id' => $course->id, 'method' => 'manual_ccp', 'coupon_code' => $coupon->code,
    ])->assertCreated();

    expect($coupon->fresh()->uses)->toBe(1);
});

it('rejects an unknown coupon code', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/checkout', [
            'course_id' => $course->id,
            'method' => 'manual_ccp',
            'coupon_code' => 'NOPE',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['coupon_code']);
});

it('fails the order cleanly when the payment provider is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $course = Course::factory()->create(['status' => 'published', 'price_dzd' => 90000]);
    $user = User::factory()->create();

    // A network failure must surface as a 502 and a failed order — never a
    // 500 that leaves the buyer with a pending order and no checkout link.
    $this->actingAs($user)->postJson('/api/checkout', [
        'course_id' => $course->id,
        'method' => 'chargily',
        'scope' => 'full',
    ])->assertStatus(502);

    $order = Order::query()->where('user_id', $user->id)->latest('id')->first();

    expect($order)->not->toBeNull()
        ->and($order->status)->toBe('failed')
        ->and($order->chargily_checkout_id)->toBeNull();
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
    $course = Course::factory()->create(['price_dzd' => 50000, 'price_eur' => 199]);
    $coupon = Coupon::factory()->create(['percent_off' => 50, 'max_uses' => 1, 'uses' => 0]);

    // Same contract as the Chargily outage: a 502 and a failed order, never a
    // 500 that strands the buyer on a pending one with no checkout link.
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

it('fails the order cleanly when the HTTP layer itself throws on the way to Chargily', function () {
    // What a proxy refusing the tunnel looks like from Laravel: an exception
    // raised before any status is read, not a response with a 4xx/5xx.
    Http::fake(fn () => throw new RequestException(
        new Illuminate\Http\Client\Response(new Response(403, [], 'Forbidden'))
    ));

    $user = User::factory()->create();
    $course = Course::factory()->create(['price_dzd' => 50000]);

    $this->actingAs($user)
        ->postJson('/api/checkout', ['course_id' => $course->id, 'method' => 'chargily'])
        ->assertStatus(502)
        ->assertJsonPath('message', __('The payment provider is unavailable. Please try again later.'));

    expect(Order::query()->sole()->status)->toBe('failed');
});
