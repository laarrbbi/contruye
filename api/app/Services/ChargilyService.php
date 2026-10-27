<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Minimal client for Chargily Pay v2 (CIB / Edahabia hosted checkout).
 * Docs: https://dev.chargily.com/pay-v2
 */
class ChargilyService
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $baseUrl,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('services.chargily.secret'),
            rtrim((string) config('services.chargily.base_url'), '/'),
        );
    }

    /**
     * Create a hosted checkout and return the raw payload
     * (id + checkout_url are the fields we rely on).
     *
     * @param  array<string, mixed>  $metadata
     * @return array{id: string, checkout_url: string}
     */
    public function createCheckout(
        int $amountDzd,
        string $successUrl,
        string $failureUrl,
        string $webhookEndpoint,
        array $metadata = [],
    ): array {
        try {
            $response = Http::withToken($this->secretKey)
                ->acceptJson()
                // Shorter than Laravel's 30 s default: a buyer is watching a
                // spinner. Deliberately NOT retried — creating a checkout is
                // not idempotent, and a retry could bill twice.
                ->connectTimeout(5)
                ->timeout(12)
                ->post("{$this->baseUrl}/checkouts", [
                    'amount' => $amountDzd,
                    'currency' => 'dzd',
                    'success_url' => $successUrl,
                    'failure_url' => $failureUrl,
                    'webhook_endpoint' => $webhookEndpoint,
                    'metadata' => $metadata,
                ]);
        } catch (ConnectionException $e) {
            // Network failure is a RuntimeException to callers, like every
            // other way this call can fail — otherwise it escapes their catch
            // and leaves the order stuck pending.
            throw new RuntimeException('Chargily unreachable: '.$e->getMessage(), previous: $e);
        } catch (Throwable $e) {
            // Anything else the HTTP layer throws before a status can be read
            // (a proxy refusing the tunnel, TLS, redirects) is raised by
            // Laravel as a RequestException. It escaped as a 500 that showed
            // the buyer "HTTP request returned status code 403".
            throw new RuntimeException('Chargily request failed: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'Chargily checkout creation failed: '.$response->status().' '.$response->body(),
            );
        }

        $data = $response->json();

        if (! isset($data['id'], $data['checkout_url'])) {
            throw new RuntimeException('Chargily checkout response missing id/checkout_url.');
        }

        return $data;
    }

    /**
     * Verify the HMAC signature Chargily sends in the "signature" header.
     * Must be called on the RAW request body before any processing.
     */
    public function verifyWebhookSignature(string $rawPayload, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        // A blank secret would make hash_hmac(..., '') a value anyone can
        // compute, so every forged webhook would verify. Fail closed: an
        // unconfigured provider must accept nothing, not everything.
        if ($this->secretKey === '') {
            Log::error('Chargily webhook rejected: CHARGILY_SECRET is not configured.');

            return false;
        }

        $computed = hash_hmac('sha256', $rawPayload, $this->secretKey);

        return hash_equals($computed, $signature);
    }
}
