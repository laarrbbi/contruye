<?php

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * The SPA's translation files (web/src/i18n), read by the server so a page it
 * renders says exactly what the app says. Spanish is the authored locale and
 * the fallback for any key missing elsewhere, as in the app.
 */
final class SpaStrings
{
    /** @var array<string, array<string, mixed>> */
    private static array $files = [];

    public static function get(string $locale, string $key): string
    {
        $value = Arr::get(self::file($locale), $key) ?? Arr::get(self::file('es'), $key);

        return is_string($value) ? $value : $key;
    }

    /** @return list<string> */
    public static function faqKeys(): array
    {
        return json_decode((string) file_get_contents(config('seo.faq')), true, flags: JSON_THROW_ON_ERROR)['keys'];
    }

    /** @return array<string, mixed> */
    private static function file(string $locale): array
    {
        return self::$files[$locale] ??= json_decode(
            (string) file_get_contents(config('seo.translations')."/{$locale}.json"),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
