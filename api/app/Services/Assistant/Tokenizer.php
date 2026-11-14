<?php

namespace App\Services\Assistant;

use Normalizer;

/**
 * Words for the course index and for the questions, the same way on both
 * sides: lower case, without accents, without the commonest short words,
 * with Spanish plurals folded («solares» → «solar», «costes» → «coste»,
 * «promociones» → «promocion»). Cyrillic and Arabic words are kept as written.
 */
final class Tokenizer
{
    private const STOPWORDS = [
        // es
        'a', 'al', 'algo', 'ante', 'como', 'con', 'cual', 'cuando', 'de', 'del', 'desde', 'donde', 'el', 'ella', 'en', 'entre', 'era', 'es', 'esa', 'ese', 'eso', 'esta', 'este', 'esto', 'fue', 'ha', 'hay', 'la', 'las', 'le', 'les', 'lo', 'los', 'mas', 'me', 'mi', 'muy', 'no', 'nos', 'o', 'para', 'pero', 'por', 'que', 'se', 'si', 'sin', 'sobre', 'son', 'su', 'sus', 'tambien', 'te', 'tu', 'un', 'una', 'uno', 'unos', 'y', 'ya', 'yo',
        // en
        'an', 'and', 'are', 'be', 'do', 'for', 'how', 'i', 'in', 'is', 'it', 'of', 'on', 'or', 'the', 'to', 'what', 'with',
        // fr
        'au', 'aux', 'ce', 'des', 'du', 'est', 'et', 'il', 'je', 'les', 'mon', 'ne', 'ou', 'pas', 'pour', 'qui', 'sur', 'une',
    ];

    /** @return array<string, int> term => count */
    public static function counts(string $text): array
    {
        $counts = [];
        foreach (self::terms($text) as $term) {
            $counts[$term] = ($counts[$term] ?? 0) + 1;
        }

        return $counts;
    }

    /** @return list<string> */
    public static function terms(string $text): array
    {
        $text = mb_strtolower(self::plain($text));
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $terms = [];
        foreach ($words as $word) {
            if (mb_strlen($word) < 2 && ! ctype_digit($word)) {
                continue;
            }
            if (in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            $terms[] = mb_substr(self::stem($word), 0, 64);
        }

        return $terms;
    }

    /** NFKC, no accents on Latin letters, no zero-width or control characters. */
    public static function plain(string $text): string
    {
        $text = Normalizer::normalize($text, Normalizer::FORM_KC) ?: $text;
        $text = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{FEFF}]/u', '', $text) ?? $text;
        $decomposed = Normalizer::normalize($text, Normalizer::FORM_D) ?: $text;

        // Combining marks out, but only on Latin letters (Cyrillic й and Arabic stay whole).
        return Normalizer::normalize(preg_replace('/(?<=[a-zA-Z])\p{Mn}+/u', '', $decomposed) ?? $decomposed, Normalizer::FORM_C) ?: $text;
    }

    private static function stem(string $word): string
    {
        if (! preg_match('/^[a-z]+$/', $word) || strlen($word) <= 4 || ! str_ends_with($word, 's')) {
            return $word;
        }
        $word = substr($word, 0, -1);
        // «solares», «leyes», «promociones», «redes»: the plural added -es after l, n, r, d, z, y.
        if (str_ends_with($word, 'e') && strlen($word) > 3 && in_array($word[strlen($word) - 2], ['l', 'n', 'r', 'd', 'z', 'y'], true)) {
            $word = substr($word, 0, -1);
        }

        return $word;
    }
}
