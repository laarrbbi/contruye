<?php

use App\Jobs\ProcessPaymentEvent;
use App\Mail\PaymentConfirmedMail;
use App\Models\Order;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;

/**
 * Post a raw signed payload to the Chargily webhook. The body is signed
 * exactly as sent so the HMAC verification runs against the real bytes.
 */
function postChargilyWebhook(array|string $payload, ?string $signature = null)
{
    $raw = is_string($payload) ? $payload : json_encode($payload);
    $signature ??= hash_hmac('sha256', $raw, 'chargily-test-secret');

    return test()->call(
        'POST',
        '/api/webhooks/chargily',
        [],
        [],
        [],
        [
            'HTTP_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ],
        $raw,
    );
}

beforeEach(function () {
    config(['services.chargily.secret' => 'chargily-test-secret']);
    Mail::fake();
});

it('marks the order paid, enrolls the user and queues the mail on checkout.paid', function () {
    $order = Order::factory()->chargily()->create();

    postChargilyWebhook([
        'id' => 'evt_1',
        'type' => 'checkout.paid',
        'data' => [
            'id' => $order->chargily_checkout_id,
            'metadata' => ['order_id' => $order->id],
        ],
    ])->assertOk()->assertJson(['received' => true]);

    $order->refresh();

    expect($order->status)->toBe('paid')
        ->and($order->paid_at)->not->toBeNull();

    $this->assertDatabaseHas('enrollments', [
        'user_id' => $order->user_id,
        'course_id' => $order->course_id,
        'order_id' => $order->id,
    ]);

    Mail::assertQueued(PaymentConfirmedMail::class, function ($mail) use ($order) {
        return $mail->hasTo($order->user->email) && $mail->order->is($order);
    });
});

it('stays idempotent on double delivery: one enrollment, one mail', function () {
    $order = Order::factory()->chargily()->create();

    $payload = [
        'id' => 'evt_1',
        'type' => 'checkout.paid',
        'data' => [
            'id' => $order->chargily_checkout_id,
            'metadata' => ['order_id' => $order->id],
        ],
    ];

    postChargilyWebhook($payload)->assertOk();
    postChargilyWebhook($payload)->assertOk();

    expect($order->fresh()->status)->toBe('paid');

    $this->assertDatabaseCount('enrollments', 1);
    Mail::assertQueued(PaymentConfirmedMail::class, 1);
});

it('finds the order by chargily checkout id when metadata is missing', function () {
    $order = Order::factory()->chargily()->create();

    postChargilyWebhook([
        'id' => 'evt_1',
        'type' => 'checkout.paid',
        'data' => ['id' => $order->chargily_checkout_id],
    ])->assertOk();

    expect($order->fresh()->status)->toBe('paid');
});

it('rejects an invalid signature and leaves the order untouched', function () {
    $order = Order::factory()->chargily()->create();

    postChargilyWebhook([
        'id' => 'evt_1',
        'type' => 'checkout.paid',
        'data' => [
            'id' => $order->chargily_checkout_id,
            'metadata' => ['order_id' => $order->id],
        ],
    ], 'not-the-right-signature')->assertForbidden();

    expect($order->fresh()->status)->toBe('pending');

    $this->assertDatabaseCount('enrollments', 0);
    Mail::assertNotQueued(PaymentConfirmedMail::class);
});

it('marks the order failed on checkout.failed', function () {
    $order = Order::factory()->chargily()->create();

    postChargilyWebhook([
        'id' => 'evt_1',
        'type' => 'checkout.failed',
        'data' => [
            'id' => $order->chargily_checkout_id,
            'metadata' => ['order_id' => $order->id],
        ],
    ])->assertOk();

    expect($order->fresh()->status)->toBe('failed');
});

it('keeps a paid order paid when checkout.failed arrives late', function () {
    $order = Order::factory()->chargily()->paid()->create();

    postChargilyWebhook([
        'id' => 'evt_1',
        'type' => 'checkout.failed',
        'data' => [
            'id' => $order->chargily_checkout_id,
            'metadata' => ['order_id' => $order->id],
        ],
    ])->assertOk();

    expect($order->fresh()->status)->toBe('paid');
});

it('acknowledges and ignores unknown event types', function () {
    $order = Order::factory()->chargily()->create();

    postChargilyWebhook([
        'id' => 'evt_1',
        'type' => 'checkout.canceled',
        'data' => ['id' => $order->chargily_checkout_id],
    ])->assertOk()->assertJson(['received' => true]);

    expect($order->fresh()->status)->toBe('pending');
});

it('still sends the confirmation when queueing it failed the first time', function () {
    $order = Order::factory()->chargily()->create(['amount' => 90000, 'currency' => 'DZD', 'status' => 'pending']);
    $payload = ['type' => 'checkout.paid', 'data' => ['metadata' => ['order_id' => $order->id]]];

    // First attempt: the mail cannot be queued (the queue backend is
    // unreachable). The job fails and will be retried.
    // The real mailer, not the file's Mail::fake(), so queueing really runs.
    Mail::swap(new MailManager(app()));
    config(['queue.default' => 'unreachable']);
    expect(fn () => (new ProcessPaymentEvent('chargily', $payload))->handle())->toThrow(InvalidArgumentException::class);
    config(['queue.default' => 'sync']);

    // Nothing was half-done: the order is not paid without its mail.
    expect($order->fresh()->status)->toBe('pending');

    // The retry settles the order and queues the confirmation.
    Mail::fake();
    (new ProcessPaymentEvent('chargily', $payload))->handle();

    expect($order->fresh()->status)->toBe('paid');
    Mail::assertQueued(PaymentConfirmedMail::class);
});
