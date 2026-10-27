<?php

namespace App\Jobs;

use App\Mail\PaymentConfirmedMail;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Processes a signature-verified payment webhook payload (Chargily or
 * Stripe). Safe to run multiple times for the same event: enrollment is
 * granted through Order::markPaidAndEnroll(), which is idempotent, and the
 * confirmation mail is only queued when that call actually transitions the
 * order to paid — in the same transaction, so a retry after a failed queue
 * write still sends it.
 */
class ProcessPaymentEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @param  'chargily'|'stripe'  $provider
     * @param  array<string, mixed>  $payload  decoded webhook payload / event
     */
    public function __construct(
        public readonly string $provider,
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        $type = $this->payload['type'] ?? null;

        // Chargily nests the checkout under `data`; Stripe under `data.object`.
        $object = $this->provider === 'stripe'
            ? ($this->payload['data']['object'] ?? [])
            : ($this->payload['data'] ?? []);

        [$paidEvent, $failedEvent] = $this->provider === 'stripe'
            ? ['checkout.session.completed', 'checkout.session.expired']
            : ['checkout.paid', 'checkout.failed'];

        if (! is_array($object)) {
            $object = [];
        }

        match ($type) {
            $paidEvent => $this->markPaid($object),
            $failedEvent => $this->markFailed($object),
            default => Log::info('Ignoring unhandled payment webhook event', [
                'provider' => $this->provider,
                'type' => $type,
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function markPaid(array $object): void
    {
        $order = $this->findOrder($object);

        if ($order === null) {
            Log::warning('Payment webhook references an unknown order', [
                'provider' => $this->provider,
                'object_id' => $object['id'] ?? null,
            ]);

            return;
        }

        if (! $this->amountMatches($order, $object)) {
            Log::warning('Payment webhook amount does not match the order; ignored', [
                'provider' => $this->provider,
                'order_id' => $order->id,
                'order_amount' => $order->amount,
                'order_currency' => $order->currency,
            ]);

            return;
        }

        // One transaction for the transition and the queued mail. Queued
        // after the commit, a failure to queue left the order paid, the
        // retried job saw "already paid", and the buyer was never told.
        DB::transaction(function () use ($order): void {
            if ($order->markPaidAndEnroll()) {
                Mail::to($order->user)->queue(new PaymentConfirmedMail($order));
            }
        });
    }

    /** The gateway that signed this event must be the one the order was placed with. */
    private function belongsToProvider(Order $order): bool
    {
        return $order->method === $this->provider;
    }

    /**
     * The amount actually collected must equal what was owed, in the same
     * currency. Without this, a signed event for a 1 DZD checkout would settle
     * a 90 000 DZD order.
     *
     * @param  array<string, mixed>  $object
     */
    private function amountMatches(Order $order, array $object): bool
    {
        $currency = strtoupper((string) ($object['currency'] ?? $order->currency));

        if ($currency !== strtoupper((string) $order->currency)) {
            return false;
        }

        $paid = $object['amount_total'] ?? $object['amount'] ?? null;

        if ($paid === null) {
            // The provider did not state an amount; the signature is still the
            // authority, so this is not treated as a mismatch.
            return true;
        }

        // Stripe quotes minor units (cents); Chargily quotes whole dinars.
        $expected = $this->provider === 'stripe' ? $order->amount * 100 : $order->amount;

        return (int) $paid === (int) $expected;
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function markFailed(array $object): void
    {
        $order = $this->findOrder($object);

        if ($order !== null && $order->status !== 'paid') {
            $order->markFailed();
        }
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function findOrder(array $object): ?Order
    {
        $orderId = $object['metadata']['order_id'] ?? null;

        if ($orderId !== null) {
            $order = Order::query()->find($orderId);

            // The order id travels in attacker-influenced metadata, so it must
            // also belong to the provider that signed this event — otherwise a
            // Stripe webhook could settle a cash or CCP order.
            if ($order !== null && $this->belongsToProvider($order)) {
                return $order;
            }
        }

        // Fallback: match on the provider's checkout/session id.
        $externalId = $object['id'] ?? null;

        if ($externalId === null) {
            return null;
        }

        $column = $this->provider === 'stripe' ? 'stripe_session_id' : 'chargily_checkout_id';

        return Order::query()->where($column, $externalId)->first();
    }
}
