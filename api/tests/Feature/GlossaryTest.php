<?php

use App\Support\Markdown;

/*
 * The «i» after each tax and institution the course text names: the words
 * unchanged, the explanation in the reader's language, only in the course
 * text in the app (never in an e-mail or a public page).
 */

it('marks a tax with its name and explanation, once per paragraph, the words unchanged', function () {
    $html = Markdown::toHtml("Pagas ITP si el vendedor es un particular; el ITP lo fija la comunidad.\n\nY el ITP otra vez.", glossary: true);

    expect(substr_count($html, 'class="term"'))->toBe(2)
        ->and($html)->toContain('<span class="term" tabindex="0">ITP<span class="term-i" aria-hidden="true">i</span><span class="term-tip" role="tooltip"><strong>Impuesto sobre Transmisiones Patrimoniales</strong>')
        // Taken out, the markup leaves exactly the instructor's text.
        ->and(strip_tags((string) preg_replace('#<span class="term-i".*?</span><span class="term-tip".*?</span></span>#s', '', $html)))
        ->toBe("Pagas ITP si el vendedor es un particular; el ITP lo fija la comunidad.\nY el ITP otra vez.");
});

it('explains in the reader\'s language, and leaves headings, law links and other surfaces alone', function () {
    app()->setLocale('en');
    $html = Markdown::toHtml("## El IVA de la obra\n\nLa **Ley 37/1992** (IVA) y el Registro de la Propiedad.", glossary: true);

    expect($html)->toContain('<h2>El IVA de la obra</h2>')
        ->toContain('<strong>Value Added Tax (IVA)</strong>')
        ->toContain('<strong>Land Registry (Registro de la Propiedad)</strong>')
        ->and(substr_count($html, 'class="term"'))->toBe(2)
        // Without the switch (e-mails, the public pages): no tooltip at all.
        ->and(Markdown::toHtml('El IVA de la obra.'))->not->toContain('term');
});

it('has every term explained in every language', function () {
    foreach (config('locales.supported') as $locale) {
        foreach (array_keys(config('glossary.terms')) as $key) {
            expect(trans("glossary.{$key}.name", [], $locale))->not->toBe("glossary.{$key}.name")
                ->and(trans("glossary.{$key}.text", [], $locale))->not->toBe("glossary.{$key}.text");
        }
    }
});
