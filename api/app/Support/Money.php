<?php

namespace App\Support;

use NumberFormatter;

/**
 * Amounts written the way the site writes them.
 *
 * The SPA formats prices with Intl.NumberFormat('fr-DZ' | 'ar-DZ' | 'en-DZ')
 * (web/src/lib/utils.ts); ICU gives PHP the same patterns, so an e-mail says
 * "72 000 DA" exactly like the page it links to. Its spaces are unbreakable
 * and the Arabic form carries its own direction marks: an amount can neither
 * wrap in the middle nor be read backwards inside right-to-left text, which
 * "72 000 DZD" built with number_format() was ("DZD 000 72").
 */
final class Money
{
    public static function format(int|float $amount, string $currency, ?string $locale = null): string
    {
        $formatter = new NumberFormatter(match ($locale ?? app()->getLocale()) {
            'ar' => 'ar_DZ',
            'en' => 'en_DZ',
            default => 'fr_DZ',
        }, NumberFormatter::CURRENCY);

        // Whole amounts without ",00", centimes when there are any.
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 0);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);

        return $formatter->formatCurrency($amount, $currency);
    }
}
