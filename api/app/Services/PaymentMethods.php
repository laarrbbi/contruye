<?php

namespace App\Services;

/**
 * Which checkout methods can actually take money right now.
 *
 * A method whose credentials are missing is not offered at all: a buyer who
 * picks it would get a 502 and a failed order, or — for CCP — a transfer
 * screen with no account number on it. Missing configuration disables the
 * capability; it never surfaces as an error the buyer has to interpret.
 */
class PaymentMethods
{
    /** Checkout order: the default selection is the first available. */
    public const ALL = ['chargily', 'stripe', 'manual_ccp', 'manual_baridimob'];

    /**
     * @return list<string>
     */
    public static function available(): array
    {
        return array_values(array_filter(self::ALL, self::isAvailable(...)));
    }

    public static function isAvailable(string $method): bool
    {
        return match ($method) {
            'chargily' => self::chargilyEnabled()
                && self::filled('services.chargily.api_key')
                && self::filled('services.chargily.secret'),
            'stripe' => self::stripeEnabled() && StripeService::isConfigured(),
            'manual_ccp' => self::filled('services.manual_payment.ccp_number')
                && self::filled('services.manual_payment.ccp_name'),
            'manual_baridimob' => self::filled('services.manual_payment.baridimob_rip'),
            default => false,
        };
    }

    /**
     * The owner's explicit switch. Blank keys are not "off": the deploy gate
     * fails on them unless the gateway has been switched off on purpose.
     */
    public static function chargilyEnabled(): bool
    {
        return (bool) config('services.chargily.enabled', true);
    }

    public static function stripeEnabled(): bool
    {
        return (bool) config('services.stripe.enabled', true);
    }

    private static function filled(string $key): bool
    {
        return trim((string) config($key)) !== '';
    }
}
