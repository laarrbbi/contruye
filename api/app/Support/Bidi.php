<?php

namespace App\Support;

/**
 * Text direction for text the app did not write itself (a course title, a
 * reason typed by the admin), decided on the server: e-mail clients do not
 * reliably honour dir="auto". Same rule as dir="auto": the first letter wins.
 */
final class Bidi
{
    public static function direction(string $text): string
    {
        if (preg_match('/\p{L}/u', $text, $first) !== 1) {
            return 'ltr';
        }

        return preg_match('/[\p{Arabic}\p{Hebrew}]/u', $first[0]) === 1 ? 'rtl' : 'ltr';
    }

    /**
     * Escaped text that keeps its own direction inside a sentence in the
     * other one: a French title in an Arabic e-mail stays in reading order.
     */
    public static function isolate(string $text): string
    {
        return '<span dir="'.self::direction($text).'">'.e($text).'</span>';
    }
}
