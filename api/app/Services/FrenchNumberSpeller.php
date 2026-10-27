<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Spells an amount "en toutes lettres" for the legally required closing line
 * of a facture ("Arrêtée la présente facture à la somme de…").
 *
 * Follows standard (pre-1990) French orthography — spaces, not hyphens,
 * between number words — which is what Algerian administrative documents use:
 * "quatre-vingt-dix mille deux cent cinquante dinars algériens".
 */
class FrenchNumberSpeller
{
    private const UNITS = [
        '', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
        'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
        'dix-sept', 'dix-huit', 'dix-neuf',
    ];

    private const TENS = [
        2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante',
    ];

    /**
     * @param  int  $centimes  amount in centimes, >= 0
     */
    public static function amountInWords(int $centimes, string $currency): string
    {
        if ($centimes < 0) {
            throw new InvalidArgumentException('Amount must be positive.');
        }

        [$whole, $cents] = [intdiv($centimes, 100), $centimes % 100];

        [$unit, $units] = match ($currency) {
            'DZD' => ['dinar algérien', 'dinars algériens'],
            'EUR' => ['euro', 'euros'],
            default => [$currency, $currency],
        };

        // "un million DE dinars" — the partitive appears when the spelled
        // number ends on million/milliard (round millions).
        $partitive = $whole >= 1_000_000 && $whole % 1_000_000 === 0 ? 'de ' : '';

        $phrase = self::spell($whole).' '.$partitive.($whole > 1 ? $units : $unit);

        if ($cents > 0) {
            $phrase .= ' et '.self::spell($cents).' '.($cents > 1 ? 'centimes' : 'centime');
        }

        return $phrase;
    }

    public static function spell(int $number): string
    {
        if ($number < 0 || $number >= 1_000_000_000_000) {
            throw new InvalidArgumentException("Cannot spell {$number}.");
        }

        if ($number === 0) {
            return 'zéro';
        }

        $parts = [];

        foreach ([
            [1_000_000_000, 'milliard', 'milliards', true],
            [1_000_000, 'million', 'millions', true],
            [1_000, 'mille', 'mille', false],
        ] as [$scale, $singular, $plural, $spellOne]) {
            if ($number >= $scale) {
                $count = intdiv($number, $scale);
                $number %= $scale;

                // "mille" never takes the leading "un" ("mille", not "un
                // mille") and blocks the plural of what precedes it
                // ("quatre-vingt mille"); million/milliard are nouns, so the
                // plural stays ("quatre-vingts millions") — both distinctions
                // happen to follow $spellOne.
                $prefix = ($count === 1 && ! $spellOne) ? '' : self::spellBelowThousand($count, $spellOne).' ';
                $parts[] = $prefix.($count > 1 ? $plural : $singular);
            }
        }

        if ($number > 0) {
            $parts[] = self::spellBelowThousand($number, true);
        }

        return implode(' ', $parts);
    }

    private static function spellBelowThousand(int $number, bool $isFinal): string
    {
        $parts = [];

        if ($number >= 100) {
            $hundreds = intdiv($number, 100);
            $number %= 100;

            $word = $hundreds === 1 ? 'cent' : self::UNITS[$hundreds].' cent';
            // "cents" only when "cent" is multiplied AND ends the number
            // (deux cents / deux cent trente).
            if ($hundreds > 1 && $number === 0 && $isFinal) {
                $word .= 's';
            }
            $parts[] = $word;
        }

        if ($number > 0) {
            $parts[] = self::spellBelowHundred($number, $isFinal);
        }

        return implode(' ', $parts);
    }

    private static function spellBelowHundred(int $number, bool $isFinal): string
    {
        if ($number < 20) {
            return self::UNITS[$number];
        }

        $tens = intdiv($number, 10);
        $unit = $number % 10;

        // 70–79 and 90–99 build on soixante/quatre-vingt + 10..19.
        if ($tens === 7 || $tens === 9) {
            $base = $tens === 7 ? 'soixante' : 'quatre-vingt';
            $rest = self::UNITS[10 + $unit];

            // soixante et onze — but quatre-vingt-onze.
            return $tens === 7 && $unit === 1 ? "{$base} et {$rest}" : "{$base}-{$rest}";
        }

        if ($tens === 8) {
            // "quatre-vingts" only when nothing follows and it ends the number.
            if ($unit === 0) {
                return 'quatre-vingt'.($isFinal ? 's' : '');
            }

            return 'quatre-vingt-'.self::UNITS[$unit];
        }

        $base = self::TENS[$tens];

        if ($unit === 0) {
            return $base;
        }

        // vingt et un, trente et un… (no hyphen around "et").
        return $unit === 1 ? "{$base} et un" : "{$base}-".self::UNITS[$unit];
    }
}
