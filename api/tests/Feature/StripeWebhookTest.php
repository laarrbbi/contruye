<?php

use App\Mail\PaymentConfirmedMail;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;

/**
 * Post a raw payload to the Stripe webhook with a REAL Stripe-Signature
 * header (t=<ts>,v1=<hmac-sha256 of "<ts>.<payload>">), so
 * \Stripe\Webhook::constructEvent performs genuine verification.
 */
function postStripeWebhook(array|string $payload, ?string $signatureHeader = null)
{
    $raw = is_string($payload) ? $payload : json_encode($payload);

    if ($signatureHeader === null) {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$raw, 'whsec_test');
        $signatureHeader = "t={$timestamp},v1={$signature}";
    }

    return test()->call(
        'POST',
        '/api/webhooks/stripe',
        [],
        [],
        [],
        [
            'HTTP_STRIPE_SIGNATURE' => $signatureHeader,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ],
        $raw,
    );
}

function stripeEventPayload(Order $order, string $type): array
{
    return [
        'id' => 'evt_test_1',
        'object' => 'event',
        'type' => $type,
        'data' => [
            'object' => [
                'id' => $order->stripe_session_id ?? 'cs_test_123',
                'object' => 'checkout.session',
                'metadata' => ['order_id' => (string) $order->id],
            ],
        ],
    ];
}

beforeEach(function () {
    config([
        'services.stripe.webhook_secret' => 'whsec_test',
        'services.stripe.secret' => 'sk_test_dummy',
    ]);

    Mail::fake();
});

it('marks the order paid and enrolls on checkout.session.completed', function () {
    $order = Order::factory()->create([
        'method' => 'stripe',
        'currency' => 'EUR',
        'amount' => 199,
        'stripe_session_id' => 'cs_test_123',
    ]);

    postStripeWebhook(stripeEventPayload($order, 'checkout.session.completed'))
        ->assertOk()
        ->assertJson(['received' => true]);

    $order->refresh();

    expect($order->status)->toBe('paid')
        ->and($order->paid_at)->not->toBeNull();

    $this->assertDatabaseHas('enrollments', [
        'user_id' => $order->user_id,
        'course_id' => $order->course_id,
        'order_id' => $order->id,
    ]);

    Mail::assertQueued(PaymentConfirmedMail::class, 1);
});

it('rejects a tampered payload', function () {
    $order = Order::factory()->create([
        'method' => 'stripe',
        'currency' => 'EUR',
        'amount' => 199,
        'stripe_session_id' => 'cs_test_123',
    ]);

    // Sign the legitimate payload, then send a modified body with that header.
    $original = json_encode(stripeEventPayload($order, 'checkout.session.completed'));
    $timestamp = time();
    $header = "t={$timestamp},v1=".hash_hmac('sha256', $timestamp.'.'.$original, 'whsec_test');

    $tampered = stripeEventPayload($order, 'checkout.session.completed');
    $tampered['data']['object']['metadata']['order_id'] = '999999';

    postStripeWebhook(json_encode($tampered), $header)->assertForbidden();

    expect($order->fresh()->status)->toBe('pending');

    $this->assertDatabaseCount('enrollments', 0);
    Mail::assertNotQueued(PaymentConfirmedMail::class);
});

it('marks the order failed on checkout.session.expired', function () {
    $order = Order::factory()->create([
        'method' => 'stripe',
        'currency' => 'EUR',
        'amount' => 199,
        'stripe_session_id' => 'cs_test_123',
    ]);

    postStripeWebhook(stripeEventPayload($order, 'checkout.session.expired'))->assertOk();

    expect($order->fresh()->status)->toBe('failed');
});

it('keeps a paid order paid when checkout.session.expired arrives late', function () {
    $order = Order::factory()->paid()->create([
        'method' => 'stripe',
        'currency' => 'EUR',
        'amount' => 199,
        'stripe_session_id' => 'cs_test_123',
    ]);

    postStripeWebhook(stripeEventPayload($order, 'checkout.session.expired'))->assertOk();

    expect($order->fresh()->status)->toBe('paid');
});
