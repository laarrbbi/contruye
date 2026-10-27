<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentEvent;
use App\Services\ChargilyService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Payment provider webhooks. Both endpoints verify the signature against the
 * RAW request body first, log the payload, then hand processing off to a
 * queued job so the provider gets its 200 as fast as possible.
 */
class WebhookController extends Controller
{
    public function chargily(Request $request): JsonResponse
    {
        $raw = $request->getContent();

        $valid = ChargilyService::fromConfig()
            ->verifyWebhookSignature($raw, $request->header('signature'));

        if (! $valid) {
            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $payload = json_decode($raw, true);

        Log::info('Chargily webhook received', self::traceable('chargily', $payload));

        if (is_array($payload)) {
            ProcessPaymentEvent::dispatch('chargily', $payload);
        }

        return response()->json(['received' => true]);
    }

    /**
     * What is needed to trace a payment — never the whole payload, which
     * carries the buyer's name, e-mail and card metadata into the log file.
     *
     * @param  mixed  $payload
     * @return array<string, mixed>
     */
    private static function traceable(string $provider, $payload): array
    {
        $data = is_array($payload) ? ($payload['data'] ?? []) : [];
        $object = $data['object'] ?? $data;

        return [
            'provider' => $provider,
            'type' => is_array($payload) ? ($payload['type'] ?? null) : null,
            'event_id' => is_array($payload) ? ($payload['id'] ?? null) : null,
            'object_id' => is_array($object) ? ($object['id'] ?? null) : null,
            'order_id' => is_array($object) ? ($object['metadata']['order_id'] ?? null) : null,
        ];
    }

    public function stripe(Request $request): JsonResponse
    {
        $raw = $request->getContent();

        $event = $this->stripeService()->constructEvent($raw, $request->header('Stripe-Signature'));

        if ($event === null) {
            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $payload = $event->toArray();

        Log::info('Stripe webhook received', self::traceable('stripe', $payload));

        ProcessPaymentEvent::dispatch('stripe', $payload);

        return response()->json(['received' => true]);
    }

    /**
     * Container-first so tests can bind a fake; fromConfig() otherwise
     * (scalar constructor args cannot be auto-wired).
     */
    private function stripeService(): StripeService
    {
        return app()->bound(StripeService::class)
            ? app(StripeService::class)
            : StripeService::fromConfig();
    }
}
