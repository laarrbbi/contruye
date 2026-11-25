<?php

namespace App\Services\Assistant;

use Normalizer;

/**
 * Words for the course index and for the questions, the same way on both
 * sides: lower case, without accents, without the commonest short words and
 * the words a question is asked with («qué», «cómo», «necesito», «what»,
 * «что»…), and folded so that a word finds its family:
 * - Spanish plurals («solares» → «solar», «costes» → «coste»);
 * - long Latin words lose a verb or noun ending («caducar», «caducidad»,
 *   «caduca» → «caduc»; «contratar», «contratación», «contrato» → «contrat»).
 *   Words of five letters or fewer are left whole on purpose: «coste» and
 *   «costa», «plazo» and «plaza», «solar» and «solo» are different things in
 *   this course;
 * - long Cyrillic words keep their first six letters (the case endings of
 *   Russian and Ukrainian go: «подрядчика», «подрядчиком» → «подряд»);
 * - an Arabic word loses its article («الرخصة» → «رخصة»).
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
        // What a question is asked with, not what it asks about.
        'cuanto', 'cuanta', 'cuantos', 'cuantas', 'cuales', 'quien', 'quienes', 'tengo', 'tiene', 'tienen', 'tener', 'hacer', 'hago', 'hace', 'puedo', 'puede', 'debo', 'debe', 'necesito', 'necesita', 'quiero', 'sirve', 'voy', 'pasa', 'ser', 'estar', 'esta', 'estan', 'soy', 'he', 'has', 'han', 'ni', 'e', 'u', 'vez', 'cada', 'tras', 'segun',
        'can', 'does', 'did', 'have', 'has', 'my', 'me', 'need', 'should', 'which', 'when', 'where', 'who', 'why', 'this', 'that', 'there', 'at', 'by', 'from', 'if', 'as', 'was', 'will', 'you', 'your', 'we',
        'que', 'quel', 'quelle', 'quels', 'quelles', 'quand', 'comment', 'dois', 'faut', 'puis', 'peux', 'ai', 'un', 'en', 'dans', 'par', 'avec', 'on', 'se', 'sa', 'son', 'ses', 'la', 'le', 'ma',
        // ru
        'что', 'такое', 'как', 'где', 'когда', 'кто', 'куда', 'какой', 'какая', 'какие', 'какое', 'ли', 'для', 'за', 'на', 'по', 'из', 'от', 'до', 'при', 'об', 'это', 'этот', 'эта', 'или', 'если', 'не', 'мне', 'мой', 'моя', 'нужно', 'надо', 'можно', 'её', 'ее', 'его', 'их', 'же', 'бы', 'так', 'есть', 'быть', 'также', 'то', 'во', 'со', 'ко', 'мы', 'вы', 'он', 'она', 'они',
        // uk
        'що', 'таке', 'як', 'де', 'коли', 'хто', 'куди', 'який', 'яка', 'які', 'яке', 'чи', 'та', 'або', 'якщо', 'це', 'цей', 'ця', 'мені', 'мій', 'моя', 'треба', 'потрібно', 'можна', 'її', 'його', 'їх', 'ж', 'би', 'так', 'є', 'бути', 'також', 'те', 'зі', 'із', 'ми', 'ви', 'він', 'вона', 'вони', 'і', 'й', 'в', 'у', 'з', 'до', 'від', 'про', 'під',
        // ar
        'ما', 'ماذا', 'هي', 'هو', 'هل', 'من', 'في', 'على', 'إلى', 'الى', 'عن', 'مع', 'كيف', 'متى', 'أين', 'اين', 'لماذا', 'أو', 'او', 'ثم', 'هذا', 'هذه', 'ذلك', 'التي', 'الذي', 'أن', 'ان', 'إن', 'كان', 'يكون', 'لي', 'له', 'لها', 'بعد', 'قبل', 'عند', 'كل', 'أي', 'اي', 'لا', 'لم', 'لن', 'قد',
    ];

    /**
     * Kept whole, because the course means two things: «seguridad» (y salud) is
     * not the family of «seguro» (decenal), nor a «certificación» (what the
     * builder is paid by) that of a «certificado» (final de obra).
     */
    private const WHOLE = ['seguridad', 'certificacion'];

    /** A long Latin word's ending, longest first: what is left names the family («certificar», «certificación», «certificado» → «certific»). */
    private const ENDINGS = ['amientos', 'imientos', 'amiento', 'imiento', 'aciones', 'idades', 'adores', 'adoras', 'acion', 'iendo', 'adora', 'idad', 'ador', 'ando', 'ados', 'adas', 'idos', 'idas', 'ado', 'ada', 'ido', 'ida', 'ar', 'er', 'ir'];

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
        if (preg_match('/^[a-z]+$/', $word) !== 1) {
            return self::foreign($word);
        }
        // «solares» → «solar», and the short ones too: «años», «días», «webs».
        if (strlen($word) >= 4 && str_ends_with($word, 's')) {
            $word = substr($word, 0, -1);
            // «solares», «leyes», «promociones», «redes»: the plural added -es after l, n, r, d, z, y.
            if (str_ends_with($word, 'e') && strlen($word) > 3 && in_array($word[strlen($word) - 2], ['l', 'n', 'r', 'd', 'z', 'y'], true)) {
                $word = substr($word, 0, -1);
            }
        }
        if (in_array($word, self::WHOLE, true)) {
            return $word;
        }
        if (strlen($word) >= 7) {
            foreach (self::ENDINGS as $ending) {
                if (str_ends_with($word, $ending) && strlen($word) - strlen($ending) >= 5) {
                    return substr($word, 0, -strlen($ending));
                }
            }
        }
        // «contrato», «contrata» → «contrat»; «notaría», «notario» → «notari»; «calcula», «cálculo» → «calcul».
        if (strlen($word) >= 6 && in_array($word[strlen($word) - 1], ['a', 'e', 'o'], true)) {
            return substr($word, 0, -1);
        }

        return $word;
    }

    /** Russian and Ukrainian: a long word without its case ending. Arabic: without its article. */
    private static function foreign(string $word): string
    {
        if (preg_match('/^\p{Cyrillic}+$/u', $word) === 1) {
            return mb_strlen($word) >= 7 ? mb_substr($word, 0, 6) : $word;
        }
        if (preg_match('/^\p{Arabic}+$/u', $word) === 1 && mb_strlen($word) >= 5 && str_starts_with($word, 'ال')) {
            return mb_substr($word, 2);
        }

        return $word;
    }
}
