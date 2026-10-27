<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;

function pendingOrder(string $method, int $amount = 90000, string $currency = 'DZD'): Order
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

it('refuses a Chargily webhook signed with an empty secret', function () {
    config(['services.chargily.secret' => '']);
    $order = pendingOrder('chargily');

    $body = json_encode([
        'type' => 'checkout.paid',
        'data' => ['metadata' => ['order_id' => $order->id]],
    ]);

    // The forge an attacker can compute for themselves when the secret is blank.
    $this->call('POST', '/api/webhooks/chargily', [], [], [], [
        'HTTP_SIGNATURE' => hash_hmac('sha256', $body, ''),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertForbidden();

    expect($order->fresh()->status)->toBe('pending')
        ->and(Enrollment::query()->where('user_id', $order->user_id)->count())->toBe(0);
});

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

it('ignores a correctly signed event whose amount does not match the order', function () {
    $secret = 'chargily_real_secret';
    config(['services.chargily.secret' => $secret]);
    $order = pendingOrder('chargily', 90000);

    // Genuinely signed, but records a 1 DZD payment for a 90 000 DZD order.
    $body = json_encode([
        'type' => 'checkout.paid',
        'data' => ['amount' => 1, 'currency' => 'dzd', 'metadata' => ['order_id' => $order->id]],
    ]);

    $this->call('POST', '/api/webhooks/chargily', [], [], [], [
        'HTTP_SIGNATURE' => hash_hmac('sha256', $body, $secret),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    expect($order->fresh()->status)->toBe('pending');

    // The same event with the right amount does settle it.
    $good = json_encode([
        'type' => 'checkout.paid',
        'data' => ['amount' => 90000, 'currency' => 'dzd', 'metadata' => ['order_id' => $order->id]],
    ]);

    $this->call('POST', '/api/webhooks/chargily', [], [], [], [
        'HTTP_SIGNATURE' => hash_hmac('sha256', $good, $secret),
        'CONTENT_TYPE' => 'application/json',
    ], $good)->assertOk();

    expect($order->fresh()->status)->toBe('paid');
});

it('will not let one provider settle an order placed with another', function () {
    $secret = 'stripe_real_secret';
    config(['services.stripe.webhook_secret' => $secret]);
    // A manual CCP order — no gateway should ever settle it.
    $order = pendingOrder('manual_ccp');

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
