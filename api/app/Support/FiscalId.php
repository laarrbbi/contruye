<?php

namespace App\Support;

/**
 * The fiscal id a buyer puts on their invoices. A Spanish one is checked the
 * way the tax agency computes it: a NIF/DNI (8 digits and the letter of their
 * remainder by 23), a NIE (X, Y or Z, 7 digits, the same letter) or a CIF (an
 * entity letter, 7 digits and a control digit or letter). A buyer abroad
 * types their own country's id, which has no single rule: it is kept if it
 * looks like one (4 to 20 letters and digits), as typed.
 *
 * "ES" in front (the intra-community VAT form of a Spanish id) is checked as
 * the Spanish id that follows it.
 */
final class FiscalId
{
    private const DNI_LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    /** "12.345.678-z" → "12345678Z". */
    public static function normalise(string $raw): string
    {
        return strtoupper((string) preg_replace('/[\s.\-\/]+/u', '', $raw));
    }

    public static function isValid(string $id): bool
    {
        $id = self::normalise($id);

        if (preg_match('/^ES([0-9A-Z]{9})$/', $id, $m) === 1) {
            return self::isValidSpanish($m[1]);
        }

        if (self::looksSpanish($id)) {
            return self::isValidSpanish($id);
        }

        return preg_match('/^[0-9A-Z]{4,20}$/', $id) === 1;
    }

    private static function looksSpanish(string $id): bool
    {
        return preg_match('/^(?:\d{8}[A-Z]|[XYZ]\d{7}[A-Z]|[ABCDEFGHJNPQRSUVW]\d{7}[0-9A-J])$/', $id) === 1;
    }

    private static function isValidSpanish(string $id): bool
    {
        if (preg_match('/^(\d{8})([A-Z])$/', $id, $m) === 1) {
            return self::DNI_LETTERS[(int) $m[1] % 23] === $m[2];
        }

        if (preg_match('/^([XYZ])(\d{7})([A-Z])$/', $id, $m) === 1) {
            $number = (int) (strpos('XYZ', $m[1]).$m[2]);

            return self::DNI_LETTERS[$number % 23] === $m[3];
        }

        if (preg_match('/^([ABCDEFGHJNPQRSUVW])(\d{7})([0-9A-J])$/', $id, $m) === 1) {
            return self::isValidCif($m[1], $m[2], $m[3]);
        }

        return false;
    }

    /**
     * The CIF control: digits in even places summed, digits in odd places
     * doubled and their own digits summed; 10 minus the last digit of the
     * total is the control, written as a digit or as the letter of the same
     * rank (J = 0, A = 1 … I = 9) depending on the kind of entity.
     */
    private static function isValidCif(string $kind, string $digits, string $control): bool
    {
        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $digit = (int) $digits[$i];
            if ($i % 2 === 0) {
                $double = $digit * 2;
                $sum += intdiv($double, 10) + $double % 10;
            } else {
                $sum += $digit;
            }
        }

        $value = (10 - $sum % 10) % 10;
        $letter = 'JABCDEFGHI'[$value];

        // Entities that always write a letter, those that always write a digit,
        // and the rest, which may write either.
        if (str_contains('KPQRSNW', $kind)) {
            return $control === $letter;
        }
        if (str_contains('ABEH', $kind)) {
            return $control === (string) $value;
        }

        return $control === (string) $value || $control === $letter;
    }
}
