<?php

use App\Models\AssistantChunk;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\Province;
use App\Models\ProvinceGuide;
use App\Models\User;
use App\Services\Assistant\Indexer;
use App\Services\Assistant\Retriever;
use App\Services\Assistant\Scope;
use App\Support\Course\CourseSource;

/*
 * What Nicolás's index holds beside the course text: each template of the
 * package, as its module's owners read it (what it calculates, its cells,
 * every formula, the course's example with its figures), and the offices of
 * each province phase by phase (Anexo A), which reach only the students of
 * that province.
 */

/** @return array{0: Course, 1: Module, 2: Module} */
function indexedCourse(): array
{
    $course = Course::factory()->create(['status' => 'published']);
    $one = Module::factory()->for($course)->create(['order' => 1, 'kind' => 'phase']);
    $two = Module::factory()->for($course)->create(['order' => 2, 'kind' => 'phase']);

    return [$course->fresh(), $one, $two];
}

function indexedStudent(Course $course, ?Module $module = null, ?Province $province = null, bool $admin = false): User
{
    $user = ($admin ? User::factory()->admin() : User::factory())->create(['province_id' => $province?->id]);
    if (! $admin) {
        Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id, 'module_id' => $module?->id]);
    }

    return $user;
}

/** What the retriever gives this user for the query, as one text. */
function foundFor(User $user, Course $course, string $query, string $locale = 'es'): string
{
    return collect(app(Retriever::class)->search($query, $locale, Scope::for($user, $course), 10))->pluck('text')->implode("\n");
}

/**
 * @param  array<string, list<array{0: string, 1: string}>>  $phases
 * @param  array<string, mixed>|null  $english
 */
function guidedProvince(int $code, string $name, string $ccaa, array $phases, ?array $english = null): Province
{
    // The 52 provinces come with the schema.
    $province = Province::query()->firstOrCreate(['code' => $code], ['name_es' => $name, 'name_en' => $name]);
    ProvinceGuide::query()->create([
        'province_id' => $province->id, 'ccaa' => $ccaa, 'section_es' => "### {$name} · capital: {$name}",
        'data' => ['prov' => $name, 'ccaa' => $ccaa, 'phases' => $phases], 'data_en' => $english,
    ]);

    return $province;
}

it('indexes each template under its module: what it calculates, its cells, every formula and the course example', function () {
    [$course, $one, $two] = indexedCourse();
    app(Indexer::class)->rebuild();

    $templates = AssistantChunk::query()->where('source_type', 'template')->get();
    $card = fn (Module $module, string $title) => $templates->where('module_id', $module->id)
        ->filter(fn ($piece) => str_contains($piece->title, $title))->sortBy('id')->pluck('text')->implode("\n");

    expect($templates->pluck('locale')->unique()->all())->toBe(['es'])
        ->and($templates->pluck('access')->unique()->all())->toBe(['module'])
        ->and($templates->pluck('module_id')->unique()->sort()->values()->all())->toBe([$one->id, $two->id])
        ->and($templates->firstWhere('module_id', $two->id)->path)->toBe("/learn/{$course->slug}/modules/{$two->id}/plantilla")
        // Every cell an example names has its label: a wrong cell would leave «».
        ->and($templates->pluck('text')->implode("\n"))->not->toContain('«»');

    expect($card($two, 'Plantilla 2'))
        ->toContain('(Módulo 2 · Análisis de viabilidad)')
        ->toContain('- B9 · Coste de construcción (€/m² construido) (numero, €; ejemplo de la plantilla: 1.200)')
        ->toContain('B32 · Precio máximo del solar (método residual) = MAX(0,(B21*(1-B17)-B30)/(1+B31)), con B21 = «Ventas (sin IVA)»')
        ->toContain('B9 «Coste de construcción (€/m² construido)» = 1.400')
        ->toContain('B35 «Beneficio antes de impuestos» = 1.058.742,33 €')
        ->toContain('Escenario pesimista (ventas al 90 %, obra +10 %)');
    expect($card($one, 'Números gordos'))->toContain('C17 «Coste de construcción» = 1.400')->toContain('C14 «Precio € del Solar» = 71.392,05 €')
        ->and($card($one, 'Base de datos de gestión de suelo'))->toContain('Se descarga en Excel')
        ->and($card($one, 'Plantilla 1'))->toContain("'Parámetros legales'")->toContain('B17 «');
});

it('gives the templates only to the owners of their module', function () {
    [$course, $one, $two] = indexedCourse();
    app(Indexer::class)->rebuild();
    $query = 'precio máximo del solar método residual beneficio Nicolás';

    expect(foundFor(indexedStudent($course, $two), $course, $query))->toContain('B32 · Precio máximo del solar')->toContain('1.058.742,33 €')
        ->and(foundFor(indexedStudent($course, $one), $course, $query))->not->toContain('B32 · Precio máximo del solar')->not->toContain('1.058.742,33')
        ->and(foundFor(indexedStudent($course), $course, $query))->toContain('B32 · Precio máximo del solar');
});

it('quotes in the course example only figures its lessons give', function () {
    $lessons = [];
    foreach (CourseSource::fromConfig()->modules() as $module) {
        foreach ($module['lessons'] as $lesson) {
            if (preg_match('/^Lección (\d+\.\d+)/u', $lesson['title'], $m) === 1) {
                $lessons[$m[1]] = str_replace("\u{A0}", ' ', $lesson['content']);
            }
        }
    }

    foreach (config('assistant_examples.templates') as $key => $example) {
        $text = implode("\n", array_map(fn (string $n) => $lessons[$n] ?? throw new RuntimeException("The course has no Lesson {$n}"), $example['lessons']));
        $figures = array_merge(array_values($example['results']), ...array_map(fn (array $variant) => array_values($variant['results']), $example['variants'] ?? []));
        foreach ($figures as $figure) {
            $number = trim((string) preg_replace('/\s*(€\/m²|€|%)$/u', '', $figure));
            expect(str_contains($text, $number))->toBeTrue("Plantilla {$key}: {$figure} is in none of Lección ".implode(', ', $example['lessons']));
        }
    }
});

it('gives a student the offices of their own province, in the modules they have', function () {
    [$course, $one] = indexedCourse();
    $alicante = guidedProvince(3, 'Alicante', 'Comunitat Valenciana', [
        '1' => [['Gerencia del Catastro de Alicante', 'Referencia catastral; palabra de control coral']],
        '2' => [['Colegio de Arquitectos de Alicante', 'Visado del proyecto; palabra de control ámbar']],
    ], ['prov' => 'Alicante', 'ccaa' => 'Valencian Community', 'phases' => ['1' => [['Alicante Cadastre Office', 'Cadastral reference; control word coral']]]]);
    $madrid = guidedProvince(28, 'Madrid', 'Comunidad de Madrid', ['1' => [['Gerencia del Catastro de Madrid', 'Referencia catastral; palabra de control jade']]]);
    app(Indexer::class)->rebuild();

    $pieces = AssistantChunk::query()->where('source_type', 'province')->get();
    expect($pieces)->toHaveCount(4)
        ->and($pieces->firstWhere(fn ($piece) => $piece->locale === 'es' && str_contains($piece->text, 'coral'))->only(['access', 'module_id', 'province_id', 'path', 'text']))->toBe([
            'access' => 'module', 'module_id' => $one->id, 'province_id' => $alicante->id, 'path' => "/learn/{$course->slug}/modules/{$one->id}/partners",
            'text' => "**Alicante (Comunitat Valenciana) · Módulo 1, dónde ir**\n- Gerencia del Catastro de Alicante: Referencia catastral; palabra de control coral",
        ]);

    $fromAlicante = indexedStudent($course, $one, $alicante);
    expect(foundFor($fromAlicante, $course, 'catastro palabra de control coral jade'))->toContain('coral')->not->toContain('jade')
        ->and(foundFor($fromAlicante, $course, 'cadastre control word coral', 'en'))->toContain('Alicante Cadastre Office')
        // Module 2 is not theirs; the whole course is another student's.
        ->and(foundFor($fromAlicante, $course, 'colegio de arquitectos ámbar'))->not->toContain('ámbar')
        ->and(foundFor(indexedStudent($course, null, $alicante), $course, 'colegio de arquitectos ámbar'))->toContain('ámbar')
        // Another province sees its own; a student who gave none, none.
        ->and(foundFor(indexedStudent($course, $one, $madrid), $course, 'catastro palabra de control coral jade'))->toContain('jade')->not->toContain('coral')
        ->and(foundFor(indexedStudent($course, $one), $course, 'catastro palabra de control coral jade'))->not->toContain('coral')->not->toContain('jade')
        // An admin with no province reads them all; with one, that one.
        ->and(foundFor(indexedStudent($course, admin: true), $course, 'catastro palabra de control coral jade'))->toContain('coral')->toContain('jade')
        ->and(foundFor(indexedStudent($course, province: $madrid, admin: true), $course, 'catastro palabra de control coral jade'))->toContain('jade')->not->toContain('coral');

    // Two provinces never share a cached answer.
    expect(Scope::for($fromAlicante, $course)->signature())->not->toBe(Scope::for(indexedStudent($course, $one, $madrid), $course)->signature());
});
