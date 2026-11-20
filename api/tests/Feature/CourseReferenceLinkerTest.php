<?php

use App\Support\Course\CourseSource;
use App\Support\Markdown;

/*
 * «Lección 4.2», «Módulo 9»: a reference to another place of the course is a
 * link to it in the course text (the player opens the lesson), in every
 * language, the words unchanged; never in a heading, a law's link, code, or
 * outside the course text.
 */

it('links a lesson or module the course names, in every language, the words unchanged', function () {
    $markdown = "Autorizaciones (Lección 4.2) y método residual (Módulo 2).\n\n"
        .'Permits (Lesson 4.2), Leçon F.8, في الدرس 4.2 و الوحدة 9, в уроке 4.2, у модулі 9.';
    $html = Markdown::toHtml($markdown, glossary: true);

    expect($html)
        ->toContain('<a class="ref-curso" data-lesson="4.2" href="#leccion-4.2">Lección 4.2</a>')
        ->toContain('<a class="ref-curso" data-module="2" href="#modulo-2">Módulo 2</a>')
        ->toContain('<a class="ref-curso" data-lesson="4.2" href="#leccion-4.2">Lesson 4.2</a>')
        ->toContain('<a class="ref-curso" data-lesson="F.8" href="#leccion-F.8">Leçon F.8</a>')
        ->toContain('<a class="ref-curso" data-lesson="4.2" href="#leccion-4.2">الدرس 4.2</a>')
        ->toContain('<a class="ref-curso" data-module="9" href="#modulo-9">الوحدة 9</a>')
        ->toContain('<a class="ref-curso" data-lesson="4.2" href="#leccion-4.2">уроке 4.2</a>')
        ->toContain('<a class="ref-curso" data-module="9" href="#modulo-9">модулі 9</a>')
        ->and(substr_count($html, 'class="ref-curso"'))->toBe(8)
        ->and(html_entity_decode(strip_tags((string) $html)))->toBe(str_replace("\n\n", "\n", $markdown));
});

it('leaves headings, code, laws, plurals and decimals alone, and every surface but the course text', function () {
    $html = Markdown::toHtml("## Lección 4.3 — Inicio de las obras\n\n`Lección 2.2`, [Lección 3.1](https://www.boe.es/), Lecciones 1.1 a 1.4, Módulo 1.5.", glossary: true);

    expect($html)->not->toContain('ref-curso')
        // Without the switch (e-mails, the public pages): no link either.
        ->and(Markdown::toHtml('Mira la Lección 4.2.'))->not->toContain('ref-curso');
});

it('sends lesson 4.1 to the sectoral authorisations of lesson 4.2', function () {
    $module = CourseSource::fromConfig()->modules()[3];
    $lesson = collect($module['lessons'])->firstWhere('title', 'Lección 4.1 — La licencia de obras');

    expect(Markdown::toHtml($lesson['content'], glossary: true))
        ->toContain('<strong>Autorizaciones sectoriales (<a class="ref-curso" data-lesson="4.2" href="#leccion-4.2">Lección 4.2</a>):</strong>');
});
