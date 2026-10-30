<?php

/*
 * Every message the API can put in front of a person exists in Spanish. On
 * the platform this one was adapted from, sixteen did not: three checkout
 * errors, the review and task-limit messages, the invoice e-mail's subject,
 * body and button, and the admin's invoicing and deletion errors all reached
 * their readers in English. Nothing caught it, because each one only shows
 * up in its own corner of the product.
 *
 * The keys are written in English, so English needs no entry of its own:
 * every other supported locale is checked.
 * The strings are collected from the source the way __() receives them, so a
 * new message without a translation fails here, not in a buyer's inbox.
 */

$translatableLiterals = function (): array {
    $keys = [];

    foreach ([app_path(), resource_path('views')] as $root) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            $where = str_replace(base_path().'/', '', $file->getPathname());

            preg_match_all("/(?:__|trans|@lang)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $source, $single);
            preg_match_all('/(?:__|trans|@lang)\(\s*"((?:[^"\\\\]|\\\\.)*)"/', $source, $double);

            foreach ($single[1] as $key) {
                $keys[stripslashes($key)] = $where;
            }
            foreach ($double[1] as $key) {
                $keys[stripcslashes($key)] = $where;
            }
        }
    }

    // validation.required and the like live in lang/{locale}/*.php.
    return array_filter($keys, fn (string $key) => ! preg_match('/^[a-z_]+\.[a-z_.]+$/', $key), ARRAY_FILTER_USE_KEY);
};

$placeholders = function (string $text): array {
    preg_match_all('/:([a-zA-Z_]+)/', $text, $matches);
    $names = array_unique($matches[1]);
    sort($names);

    return $names;
};

it('finds the strings it checks', function () use ($translatableLiterals) {
    // Guards the scanner itself: a regex that matched nothing would make the
    // next test pass vacuously.
    expect(count($translatableLiterals()))->toBeGreaterThan(50)
        ->and($translatableLiterals())->toHaveKey('Your Construye Academy invoice :number');
});

it('has a translation for every message the API sends, in every locale but the keys\' own', function () use ($translatableLiterals) {
    $locales = array_diff(config('locales.supported'), ['en']);
    expect($locales)->toContain('es');

    foreach ($locales as $locale) {
        $translations = json_decode(file_get_contents(lang_path("{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

        $missing = array_diff_key($translatableLiterals(), $translations);

        expect($missing)->toBe([], "Missing from lang/{$locale}.json:\n".implode("\n", array_map(
            fn (string $key, string $where) => "  \"{$key}\"  ({$where})",
            array_keys($missing),
            $missing,
        )));
    }
});

it('keeps every placeholder in every translation', function () use ($placeholders) {
    // A translation that drops :number sends an invoice e-mail without the
    // invoice number in it; one that renames :course prints ":curso".
    foreach (config('locales.supported') as $locale) {
        $translations = json_decode(file_get_contents(lang_path("{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

        foreach ($translations as $key => $translation) {
            expect($placeholders($translation))->toBe($placeholders($key), "lang/{$locale}.json: \"{$key}\"");
        }
    }
});
