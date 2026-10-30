<?php

namespace App\Support;

/**
 * The ways to pay, as the public pages name them: the complete offer planned
 * for launch, in the app's own words (web/src/i18n, "payments"). The owner
 * decided (constitution I, amendment inherited from Ebni) that the sales page
 * presents all three before every gateway is configured, since the gateways
 * are wired just before launch. The checkout still offers only the methods that can
 * take money (App\Services\PaymentMethods). The sales page words them the
 * same way (web/src/lib/payments.ts), so the page, its FAQ and its structured
 * data say the same thing.
 */
final class PaymentWording
{
    /** Display order: the card first, then the two ways to pay from a bank app. */
    private const ORDER = ['stripe', 'manual_transfer', 'manual_bizum'];

    /** "tarjeta bancaria (Visa, Mastercard), transferencia bancaria o Bizum" */
    public static function methods(string $locale): string
    {
        $names = array_map(fn (string $method) => SpaStrings::get($locale, "payments.names.{$method}"), self::ORDER);

        return implode(SpaStrings::get($locale, 'payments.separator'), array_slice($names, 0, -1))
            .' '.SpaStrings::get($locale, 'payments.or').' '.end($names);
    }

    /** "Pago: …." followed by $rest. */
    public static function sentence(string $locale, string $rest): string
    {
        return str_replace('{{methods}}', self::methods($locale), SpaStrings::get($locale, 'payments.sentence')).' '.$rest;
    }
}
