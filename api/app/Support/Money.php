<?php

namespace App\Support;

use NumberFormatter;

/**
 * Amounts written the way the site writes them.
 *
 * The SPA formats prices with Intl.NumberFormat('es-ES' | 'en-GB')
 * (web/src/lib/utils.ts); ICU gives PHP the same patterns, so an e-mail says
 * "1.490 €" exactly like the page it links to. The space before the euro sign
 * is unbreakable: an amount never wraps in the middle, which "1490 EUR" built
 * with number_format() could.
 */
final class Money
{
    public static function format(int|float $amount, string $currency, ?string $locale = null): string
    {
        $formatter = new NumberFormatter(match ($locale ?? app()->getLocale()) {
            'en' => 'en_GB',
            default => 'es_ES',
        }, NumberFormatter::CURRENCY);

        // Whole amounts without ",00"; otherwise both cent digits, so 40.5
        // reads "40,50 €" and never "40,5 €".
        $whole = (float) $amount === floor((float) $amount);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $whole ? 0 : 2);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);

        return $formatter->formatCurrency($amount, $currency);
    }
}
