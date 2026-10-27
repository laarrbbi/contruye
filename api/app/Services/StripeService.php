<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Stripe hosted Checkout for diaspora payments (EUR).
 */
class StripeService
{
    public function __construct(
        private readonly StripeClient $client,
        private readonly string $webhookSecret,
    ) {}

    public static function fromConfig(): self
    {
        $secret = (string) config('services.stripe.secret');

        return new self(
            // The SDK throws on an empty-string key, which turned an
            // unconfigured Stripe into a 500 on the webhook route instead of
            // the 403 below. Verifying a webhook never needs the API key.
            new StripeClient($secret === '' ? [] : $secret),
            (string) config('services.stripe.webhook_secret'),
        );
    }

    /**
     * Stripe takes money only when both halves are set: the API key opens
     * the session, the webhook secret is what settles the order. One
     * without the other is a checkout that can never be paid.
     */
    public static function isConfigured(): bool
    {
        return trim((string) config('services.stripe.secret')) !== ''
            && trim((string) config('services.stripe.webhook_secret')) !== '';
    }

    /**
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(
        Order $order,
        Course $course,
        User $user,
        string $successUrl,
        string $cancelUrl,
    ): array {
        $session = $this->client->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $user->email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $order->amount * 100, // EUR cents
                    'product_data' => [
                        'name' => $course->localizedTitle($user->locale),
                    ],
                ],
            ]],
            'metadata' => ['order_id' => (string) $order->id],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        return ['id' => $session->id, 'url' => (string) $session->url];
    }

    /**
     * Verify the Stripe-Signature header against the raw payload and
     * return the decoded event, or null if the signature is invalid.
     */
    public function constructEvent(string $rawPayload, ?string $signatureHeader): ?Event
    {
        if ($signatureHeader === null || $signatureHeader === '') {
            return null;
        }

        // Stripe's SDK does not reject an empty secret: it would happily
        // verify hash_hmac(..., ''), which any attacker can produce. The
        // shipped .env.example leaves this blank, so guard it here.
        if ($this->webhookSecret === '') {
            Log::error('Stripe webhook rejected: STRIPE_WEBHOOK_SECRET is not configured.');

            return null;
        }

        try {
            return Webhook::constructEvent($rawPayload, $signatureHeader, $this->webhookSecret);
        } catch (\UnexpectedValueException|SignatureVerificationException) {
            return null;
        }
    }
}
