<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;

function pendingOrder(string $method, int $amount = 490, string $currency = 'EUR'): Order
{
    return Order::factory()->create([
        'user_id' => User::factory()->create()->id,
        'course_id' => Course::factory()->create(['status' => 'published'])->id,
        'amount' => $amount,
        'currency' => $currency,
        'method' => $method,
        'scope' => 'full',
        'status' => 'pending',
    ]);
}

it('refuses a Stripe webhook signed with an empty secret', function () {
    // Both blank, as .env.example ships them: the SDK refuses to build a
    // client on an empty API key, which once turned this 403 into a 500.
    config(['services.stripe.webhook_secret' => '', 'services.stripe.secret' => '']);
    $order = pendingOrder('stripe', 300, 'EUR');

    $body = json_encode([
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_forged', 'metadata' => ['order_id' => $order->id]]],
    ]);
    $ts = time();

    $this->call('POST', '/api/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$ts},v1=".hash_hmac('sha256', "{$ts}.{$body}", ''),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertForbidden();

    expect($order->fresh()->status)->toBe('pending')
        ->and(Enrollment::query()->where('user_id', $order->user_id)->count())->toBe(0);
});

it('will not let one provider settle an order placed with another', function () {
    $secret = 'stripe_real_secret';
    config(['services.stripe.webhook_secret' => $secret]);
    // A bank-transfer order — no gateway should ever settle it.
    $order = pendingOrder('manual_transfer');

    $body = json_encode([
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['metadata' => ['order_id' => $order->id]]],
    ]);
    $ts = time();

    $this->call('POST', '/api/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$ts},v1=".hash_hmac('sha256', "{$ts}.{$body}", $secret),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    expect($order->fresh()->status)->toBe('pending')
        ->and(Enrollment::query()->where('user_id', $order->user_id)->count())->toBe(0);
});

it('converts stripe minor units before comparing the amount collected', function () {
    $secret = 'stripe_real_secret';
    config(['services.stripe.webhook_secret' => $secret]);

    // A 199 EUR order — Stripe will quote what it collected in cents.
    $order = pendingOrder('stripe', 199, 'EUR');
    $order->update(['stripe_session_id' => 'cs_amount_1']);

    $send = function (array $object) use ($secret) {
        $body = json_encode([
            'id' => 'evt_amount',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => ['object' => $object],
        ]);
        $ts = time();

        return $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$ts},v1=".hash_hmac('sha256', "{$ts}.{$body}", $secret),
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    };

    // 199 cents is 1,99 € — a genuinely signed event for a 1,99 € checkout
    // must not settle a 199 € order.
    $send([
        'id' => 'cs_amount_1',
        'amount_total' => 199,
        'currency' => 'eur',
        'metadata' => ['order_id' => (string) $order->id],
    ])->assertOk();

    expect($order->fresh()->status)->toBe('pending');

    // The right number in the wrong currency is refused too.
    $send([
        'id' => 'cs_amount_1',
        'amount_total' => 19900,
        'currency' => 'usd',
        'metadata' => ['order_id' => (string) $order->id],
    ])->assertOk();

    expect($order->fresh()->status)->toBe('pending');

    // 199 € stated the way Stripe states it does settle the order.
    $send([
        'id' => 'cs_amount_1',
        'amount_total' => 19900,
        'currency' => 'eur',
        'metadata' => ['order_id' => (string) $order->id],
    ])->assertOk();

    expect($order->fresh()->status)->toBe('paid')
        ->and(Enrollment::query()->where('user_id', $order->user_id)->count())->toBe(1);
});
