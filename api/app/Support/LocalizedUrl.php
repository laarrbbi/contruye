<?php

namespace App\Support;

/**
 * One URL per language for the public pages: French at the root (the
 * authored language and the main market), Arabic under /ar, English under
 * /en. The app reads the same prefixes (web/src/lib/localePath.ts).
 */
final class LocalizedUrl
{
    public static function path(string $path, string $locale): string
    {
        if ($locale === config('locales.default', 'fr')) {
            return $path;
        }

        return '/'.$locale.($path === '/' ? '' : $path);
    }

    public static function to(string $path, string $locale): string
    {
        return rtrim((string) config('app.url'), '/').self::path($path, $locale);
    }

    /**
     * hreflang alternates for a page: every language, plus x-default for
     * visitors whose language is none of them.
     *
     * @return array<string, string>
     */
    public static function alternates(string $path): array
    {
        $links = [];
        foreach (config('locales.supported') as $locale) {
            $links[$locale] = self::to($path, $locale);
        }
        $links['x-default'] = self::to($path, config('locales.default', 'fr'));

        return $links;
    }
}
