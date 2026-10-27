<?php

namespace App\Support;

/**
 * Detects course passages that still need an expert (instructor / notary /
 * lawyer) sign-off.
 *
 * The programme marks these with phrases like "pendiente de validar con el
 * notario" or "importe por confirmar". Such passages are never removed: they
 * are shown to admins with an "A VALIDAR" badge (never to students) and
 * collected into the content-review report.
 *
 * "a confirmar" alone is not a marker: it sits inside "para confirmar", an
 * ordinary phrase of the course text.
 */
final class ContentValidation
{
    public const MARKERS = [
        'pendiente de validar', 'pendiente de verificar', 'pendiente de confirmar',
        'pendientes de validar', 'pendientes de verificar', 'pendientes de confirmar',
        'por validar', 'por verificar', 'por confirmar',
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
