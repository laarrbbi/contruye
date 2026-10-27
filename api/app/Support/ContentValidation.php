<?php

namespace App\Support;

/**
 * Detects course passages that still need an expert (notary / jurist) sign-off.
 *
 * The source programme marks these with phrases like "à vérifier auprès de la
 * CASNOS" or "module à verrouiller avec le notaire". Such passages are never
 * removed: they are shown to admins with an "A VALIDER" badge (never to
 * students) and collected into the content-review report.
 */
final class ContentValidation
{
    public const MARKERS = [
        'à vérifier', 'a verifier',
        'à confirmer', 'a confirmer',
        'à verrouiller', 'a verrouiller',
        'à valider', 'a valider',
    ];

    public static function flags(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }

        $haystack = mb_strtolower($text);

        foreach (self::MARKERS as $marker) {
            if (str_contains($haystack, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Annotate a list of bullet points with their validation state.
     *
     * @param  list<string>  $points
     * @return list<array{text: string, needs_validation: bool}>
     */
    public static function annotate(array $points): array
    {
        return array_values(array_map(fn (string $point) => [
            'text' => $point,
            'needs_validation' => self::flags($point),
        ], $points));
    }

    /**
     * Only the entries that need validation.
     *
     * @param  list<string>  $points
     * @return list<string>
     */
    public static function pending(array $points): array
    {
        return array_values(array_filter($points, self::flags(...)));
    }
}
