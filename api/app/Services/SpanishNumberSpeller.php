<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Spells an amount in words for the closing line of a factura ("Importe
 * total en letra: …"). Not a legal requirement in Spain, but customary, and
 * it removes any doubt about a misprinted figure.
 *
 * Follows the RAE's current rules: one word up to thirty ("veintidós",
 * "veintiséis"), "y" between tens and units from thirty-one on ("treinta y
 * cuatro"), "cien" alone but "ciento" before anything ("ciento cinco"), and
 * "uno" shortened to "un" before a noun or before "mil"/"millón": "un euro",
 * "veintiún euros", "treinta y un mil euros", "un millón de euros".
 */
class SpanishNumberSpeller
{
    private const UNITS = [
        '', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
        'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis',
        'diecisiete', 'dieciocho', 'diecinueve', 'veinte', 'veintiuno', 'veintidós',
        'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete',
        'veintiocho', 'veintinueve',
    ];

    private const TENS = [
        3 => 'treinta', 4 => 'cuarenta', 5 => 'cincuenta', 6 => 'sesenta',
        7 => 'setenta', 8 => 'ochenta', 9 => 'noventa',
    ];

    private const HUNDREDS = [
        1 => 'ciento', 2 => 'doscientos', 3 => 'trescientos', 4 => 'cuatrocientos',
        5 => 'quinientos', 6 => 'seiscientos', 7 => 'setecientos', 8 => 'ochocientos',
        9 => 'novecientos',
    ];

    /**
     * @param  int  $centimes  amount in céntimos, >= 0
     */
    public static function amountInWords(int $centimes, string $currency): string
    {
        if ($centimes < 0) {
            throw new InvalidArgumentException('Amount must be positive.');
        }

        [$whole, $cents] = [intdiv($centimes, 100), $centimes % 100];

        [$unit, $units] = match ($currency) {
            'EUR' => ['euro', 'euros'],
            default => [$currency, $currency],
        };

        // "un millón DE euros" — the preposition appears when the spelled
        // number ends on millón/millones (round millions).
        $partitive = $whole >= 1_000_000 && $whole % 1_000_000 === 0 ? 'de ' : '';

        $phrase = self::spell($whole, beforeNoun: true).' '.$partitive.($whole === 1 ? $unit : $units);

        if ($cents > 0) {
            $phrase .= ' con '.self::spell($cents, beforeNoun: true).' '.($cents === 1 ? 'céntimo' : 'céntimos');
        }

        return $phrase;
    }

    /**
     * @param  bool  $beforeNoun  a masculine noun follows ("un euro", not "uno euro")
     */
    public static function spell(int $number, bool $beforeNoun = false): string
    {
        if ($number < 0 || $number >= 1_000_000_000_000) {
            throw new InvalidArgumentException("Cannot spell {$number}.");
        }

        if ($number === 0) {
            return 'cero';
        }

        $parts = [];

        // Spanish counts in thousands of millions: "mil millones", never
        // "un billón" (which is a million millions).
        $millions = intdiv($number, 1_000_000);
        $number %= 1_000_000;

        if ($millions > 0) {
            $parts[] = $millions === 1
                ? 'un millón'
                : self::spellBelowMillion($millions, apocope: true).' millones';
        }

        if ($number > 0) {
            $parts[] = self::spellBelowMillion($number, apocope: $beforeNoun);
        }

        return implode(' ', $parts);
    }

    private static function spellBelowMillion(int $number, bool $apocope): string
    {
        $parts = [];

        if ($number >= 1000) {
            $thousands = intdiv($number, 1000);
            $number %= 1000;

            // "mil", never "un mil"; "veintiún mil", "doscientos un mil".
            $parts[] = $thousands === 1 ? 'mil' : self::spellBelowThousand($thousands, apocope: true).' mil';
        }

        if ($number > 0) {
            $parts[] = self::spellBelowThousand($number, $apocope);
        }

        return implode(' ', $parts);
    }

    private static function spellBelowThousand(int $number, bool $apocope): string
    {
        if ($number === 100) {
            return 'cien';
        }

        $parts = [];

        if ($number >= 100) {
            $parts[] = self::HUNDREDS[intdiv($number, 100)];
            $number %= 100;
        }

        if ($number > 0) {
            $parts[] = self::spellBelowHundred($number, $apocope);
        }

        return implode(' ', $parts);
    }

    private static function spellBelowHundred(int $number, bool $apocope): string
    {
        if ($number < 30) {
            $word = self::UNITS[$number];

            if ($apocope && $number === 1) {
                return 'un';
            }

            return $apocope && $number === 21 ? 'veintiún' : $word;
        }

        $tens = self::TENS[intdiv($number, 10)];
        $unit = $number % 10;

        if ($unit === 0) {
            return $tens;
        }

        return $tens.' y '.($apocope && $unit === 1 ? 'un' : self::UNITS[$unit]);
    }
}
