<?php

namespace App\Support\Legal;

use App\Support\SpaStrings;

/**
 * The laws the app's own copy names (today the FAQ: «Ley 18/2022»), for the
 * browser to link the way the server links the course: the web app has no
 * config/legal.php, so `php artisan legal:export-web` writes this list to
 * web/src/content/legal-links.json, and LegalLinksTest fails when the file
 * and the config (or the copy) part ways.
 *
 * Only what the copy actually names goes out, resolved in the sentence it
 * stands in (`when`), so the sales page carries a few lines, not 99 laws.
 */
final class WebLinks
{
    /** Where the web app reads it. */
    public static function path(): string
    {
        return base_path('../web/src/content/legal-links.json');
    }

    /** @return array{links: list<array{text: string, url: string, title: string}>} */
    public static function payload(): array
    {
        $sources = LegalSources::instance();
        $links = [];
        foreach (config('locales.supported') as $locale) {
            foreach (self::texts($locale) as $text) {
                foreach ($sources->find($text, $text) as $match) {
                    $links[$match['text']] ??= [
                        'text' => $match['text'],
                        'url' => $match['law']['url'],
                        'title' => $match['law']['title'],
                    ];
                }
            }
        }
        ksort($links);

        return ['links' => array_values($links)];
    }

    /**
     * The copy the web app links (Questions.tsx: the FAQ answers).
     *
     * @return list<string>
     */
    private static function texts(string $locale): array
    {
        return array_map(fn (string $key) => SpaStrings::get($locale, "course.faq.{$key}.a"), SpaStrings::faqKeys());
    }
}
