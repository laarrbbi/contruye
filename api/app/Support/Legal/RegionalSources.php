<?php

namespace App\Support\Legal;

/**
 * The official pages of each autonomous community (config/legal.php,
 * `regions`): its planning law, its tax agency's ITP and AJD page and its
 * protected-housing maximum prices. Ceuta and Melilla have no housing page
 * of their own, so none is listed: a link is only ever an official one.
 */
final class RegionalSources
{
    /** @return list<array{kind: string, url: string, title: string, lang: string}> */
    public static function for(?string $region): array
    {
        $config = $region === null ? null : config("legal.regions.{$region}");
        if ($config === null) {
            return [];
        }

        $links = [];
        $law = config('legal.laws.'.$config['planning_law']);
        if ($law !== null) {
            $links[] = ['kind' => 'planning', 'url' => $law['url'], 'title' => $law['title'], 'lang' => 'es'];
        }

        return [...$links, ...array_map(fn (array $link) => [
            'kind' => $link['kind'],
            'url' => $link['url'],
            'title' => $link['title'],
            // The language of the official title: Catalan on some pages, Spanish otherwise.
            'lang' => $link['lang'] ?? 'es',
        ], $config['links'])];
    }

    /** @return list<array{key: string, name: string, links: list<array{kind: string, url: string, title: string, lang: string}>}> */
    public static function all(): array
    {
        return array_map(
            fn (string $key) => ['key' => $key, 'name' => config("legal.regions.{$key}.name"), 'links' => self::for($key)],
            array_keys(config('legal.regions', [])),
        );
    }
}
