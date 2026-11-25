<?php

use App\Services\Assistant\Chunker;
use App\Services\Assistant\Indexer;
use App\Services\Assistant\Retriever;
use App\Services\Assistant\Tokenizer;
use App\Services\Assistant\Tools;

/*
 * The search is only as good as the pieces it is given and the words it
 * keeps: a piece must read on its own, and a word must find its family
 * without being mistaken for another.
 */

it('leaves a short text whole', function () {
    expect(Chunker::split("Un párrafo.\n\nY otro.", 1200))->toBe(["Un párrafo.\n\nY otro."]);
});

it('never parts a list item from its lines, and says the heading again where a piece starts under it', function () {
    $filler = str_repeat('Texto de relleno para que no quepa todo en una pieza. ', 6);
    $text = "Introducción. {$filler}\n\n- **Silencio administrativo:** es negativo. {$filler}\n- **ICIO:**\n  - Base: coste de ejecución material.\n  - Se devenga al iniciar la obra.\n\n"
        ."### Ejemplo: Nicolás, el promotor\n\nPrimer párrafo del ejemplo. {$filler}\n\n**El ICIO.** Lo liquidó en gestión tributaria. {$filler}{$filler}";

    $pieces = Chunker::split($text, 700);
    $icio = collect($pieces)->first(fn (string $piece) => str_contains($piece, 'Se devenga'));
    $example = collect($pieces)->filter(fn (string $piece) => str_contains($piece, 'Ejemplo: Nicolás'))->values();

    // «ICIO:» and its lines are one piece: the lines alone would not say what they are about.
    expect($icio)->toContain('- **ICIO:**')->toContain('- Base: coste de ejecución material.')
        // The example starts its own piece, under its heading; the piece that goes on says the heading again, marked.
        ->and($example)->toHaveCount(2)
        ->and($example[0])->toStartWith('### Ejemplo: Nicolás, el promotor'."\n\n".'Primer párrafo')
        ->and($example[1])->toStartWith('### Ejemplo: Nicolás, el promotor (…)'."\n\n".'**El ICIO.**')
        // Nothing of the text is lost or changed.
        ->and(str_replace([' (…)', '### Ejemplo: Nicolás, el promotor'], '', implode('', $pieces)))->toContain('Lo liquidó en gestión tributaria.');
});

it('gives every piece of a table its header, and every piece of a long list what it lists', function () {
    $rows = implode("\n", array_map(fn (int $n) => "| Institución {$n} | Qué pides en la institución número {$n}, con detalle | {$n} meses |", range(1, 12)));
    $table = Chunker::split("## Dónde ir\n\n| Institución | Qué pides | Plazo |\n|---|---|---|\n{$rows}", 400);

    expect(count($table))->toBeGreaterThan(2);
    foreach ($table as $index => $piece) {
        expect($piece)->toContain('| Institución | Qué pides | Plazo |')->toContain('|---|---|---|')
            ->and($piece)->toStartWith($index === 0 ? '## Dónde ir'."\n" : '## Dónde ir (…)');
    }

    $cells = implode("\n", array_map(fn (int $n) => "- B{$n} · Casilla número {$n} de la plantilla (numero, €; ejemplo de la plantilla: {$n}.000)", range(4, 18)));
    $list = Chunker::split("**Entradas** (las casillas que rellena el alumno):\n{$cells}", 500);
    expect(count($list))->toBeGreaterThan(2)
        ->and($list[0])->toStartWith('**Entradas** (las casillas que rellena el alumno):'."\n".'- B4')
        ->and($list[1])->toStartWith('**Entradas** (las casillas que rellena el alumno): (…)'."\n".'- B')
        ->and(collect($list)->last())->toContain('- B18 ·');
});

it('folds a word into its family, and keeps apart the ones the course tells apart', function () {
    $family = fn (string $words) => array_values(array_unique(Tokenizer::terms($words)));

    expect($family('caducar caducidad caduca'))->toBe(['caduc'])
        ->and($family('contrato contratar contratación'))->toBe(['contrat'])
        ->and($family('notaría notario'))->toBe(['notari'])
        ->and($family('solares solar'))->toBe(['solar'])
        ->and($family('años año'))->toBe(['ano'])
        // Russian and Ukrainian case endings, Arabic article.
        ->and($family('подрядчика подрядчиком'))->toBe(['подряд'])
        ->and($family('підрядника підрядником'))->toBe(['підряд'])
        ->and($family('الرخصة رخصة'))->toBe(['رخصة'])
        // Different things in this course: never one family.
        ->and($family('coste costa'))->toBe(['coste', 'costa'])
        ->and($family('plazo plaza'))->toBe(['plazo', 'plaza'])
        ->and($family('seguro seguridad'))->toBe(['segur', 'seguridad'])
        ->and($family('certificado certificación'))->toBe(['certific', 'certificacion'])
        // What a question is asked with is not what it asks about.
        ->and($family('¿Qué es y cuándo se paga el ICIO?'))->toBe(['paga', 'icio'])
        ->and($family('What is the AEAT and when do I need it?'))->toBe(['aeat'])
        ->and($family('Что такое nota simple и где её получить?'))->toBe(['nota', 'simpl', 'получи']);
});

it('knows where a word of a piece stands: its lines, its title, its headings and bold openings', function () {
    $zones = Indexer::zones('Módulo 7 · Lección 7.2 — El OCT y el control de calidad', "- **ICIO:**\n  - Se devenga al iniciar la obra.\n\n### Resumen\n\nEl OCT revisa la obra.");

    expect($zones['oct'])->toBe([1, 1, 0])
        ->and($zones['icio'])->toBe([1, 0, 1])
        ->and($zones['resumen'])->toBe([1, 0, 1])
        ->and($zones['deveng'])->toBe([1, 0, 0])
        ->and($zones['calidad'])->toBe([0, 1, 0]);
});

it('hears a template named and a question that asks where, in the course\'s languages', function () {
    expect(Tools::namedTemplates('Calcula con la plantilla 2 y con los números gordos'))->toBe(['Plantilla 2 ·', 'Números gordos'])
        ->and(Tools::namedTemplates('What does Template 3 need?'))->toBe(['Plantilla 3 ·'])
        ->and(Tools::namedTemplates('Что считает Шаблон 5?'))->toBe(['Plantilla 5 ·'])
        ->and(Tools::namedTemplates('Comment remplir le modèle professionnel ?'))->toBe(['Plantilla profesional'])
        ->and(Tools::namedTemplates('¿Qué es el ICIO?'))->toBe([])
        ->and(Retriever::asksWhere('¿A qué oficina voy para la licencia?'))->toBeTrue()
        ->and(Retriever::asksWhere('Where do I apply for the licence?'))->toBeTrue()
        ->and(Retriever::asksWhere('Куда обращаться за разрешением?'))->toBeTrue()
        ->and(Retriever::asksWhere('¿Qué es el seguro decenal?'))->toBeFalse();
});
