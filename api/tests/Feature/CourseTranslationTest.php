<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\User;
use App\Support\Course\CourseSource;
use App\Support\Course\CourseTranslation;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\CourseTranslationSeeder;
use Database\Seeders\CurriculumV2Seeder;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Support\Facades\File;

/*
 * The translations in traducciones/ are ours, not the instructor's: drafts,
 * with the Spanish as the reference. Two things hold them to it. Every
 * translated piece keeps every figure, law number and acronym of its Spanish
 * original, its table rows and its checklist items (constitution II: a legal
 * figure is quoted, never reworded, in any language). And a text not
 * translated yet reads the Spanish, said as such, never a blank.
 */

it('checks every translation in the repository against the Spanish, and finds them sound', function () {
    expect(CourseTranslation::available())->toBe(['fr', 'ar', 'ru', 'uk']);

    foreach (CourseTranslation::available() as $locale) {
        $translation = CourseTranslation::fromConfig($locale);

        // Each call throws, naming file and section, at the first lost figure.
        expect($translation->modules())->toHaveCount(13)
            ->and($translation->course()['title'])->not->toBeEmpty();
        $translation->documents();
        $translation->assessments();
    }
});

it('refuses a translation that changes a law number, drops an acronym, a table row or a checklist item', function () {
    // The Spanish is a faithful "translation" of itself: it passes, so each
    // failure below is the one change made to it.
    $spanish = CourseSource::fromConfig()->read('1_contenido/modulos/10_preentrega.md');
    $parsed = CourseTranslation::parseModule($spanish, $spanish, 'test');
    expect($parsed['lessons'])->toHaveCount(3)
        ->and($parsed['checklist'])->toHaveCount(6)
        ->and($parsed['lessons'][2]['content'])->toContain('Ley 5/2019');

    $broken = [
        [str_replace('Ley 5/2019', 'Ley 5/2020', $spanish), 'the figure 5/2019 is missing'],
        [str_replace('DNI o NIE', 'DNI', $spanish), 'NIE is missing'],
        [str_replace('10 días naturales', 'diez días naturales', $spanish), 'the figure 10 is missing'],
        [preg_replace('/^\\| \\*\\*Notaría\\*\\* .*\\n/m', '', $spanish), 'table rows'],
        [str_replace("- [ ] Notarías y fechas confirmadas\n", '', $spanish), 'checklist items'],
        [str_replace('## Lección 10.2 —', '## Lección 10.9 —', $spanish), 'number 10.2'],
        [preg_replace('/^## Dónde ir\\n\\n(?:(?!^## ).*\\n)*/m', '', $spanish), 'sections'],
    ];

    foreach ($broken as [$translation, $message]) {
        expect($translation)->not->toBe($spanish);
        expect(fn () => CourseTranslation::parseModule($translation, $spanish, 'test'))
            ->toThrow(RuntimeException::class, $message);
    }
});

it('refuses a translated quiz that moves the right answer', function () {
    $root = storage_path('framework/testing/traducciones-'.uniqid());
    $quizzes = CourseSource::fromConfig()->json('1_contenido/evaluacion/quizzes_por_modulo.json');
    File::ensureDirectoryExists("{$root}/fr/evaluacion");
    config(['curso.translations' => $root]);

    try {
        // Kept as written: accepted.
        File::put("{$root}/fr/evaluacion/quizzes_por_modulo.json", json_encode([$quizzes[0]]));
        expect(CourseTranslation::fromConfig('fr')->assessments()['quizzes'])->toHaveKey($quizzes[0]['seccion']);

        // B was right in the Spanish; the "translation" makes A right.
        $moved = $quizzes[0];
        $moved['preguntas'][0]['opciones'][0]['correcta'] = true;
        $moved['preguntas'][0]['opciones'][1]['correcta'] = false;
        File::put("{$root}/fr/evaluacion/quizzes_por_modulo.json", json_encode([$moved]));
        expect(fn () => CourseTranslation::fromConfig('fr')->assessments())
            ->toThrow(RuntimeException::class, 'right answer');
    } finally {
        File::deleteDirectory($root);
    }
});

it('serves a student the translated text, and the Spanish, labelled as such, where none exists yet', function () {
    $this->seed([ProvinceSeeder::class, CurriculumV2Seeder::class, ChecklistSeeder::class, CourseTranslationSeeder::class]);
    $course = Course::query()->where('slug', 'promocion-inmobiliaria-espana')->firstOrFail();
    $student = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'module_id' => null]);

    $learn = fn (string $locale) => $this->actingAs($student)->withHeader('Accept-Language', $locale)
        ->getJson("/api/courses/{$course->slug}/learn")->assertOk()->json('data');

    $russian = $learn('ru');
    $module = $russian['modules'][0];
    $lessons = $module['lessons'];

    // Translated: the free preview lessons, every title, objective and checklist.
    expect($russian['course']['title'])->toBe('Девелопмент рыночного жилья в Испании')
        ->and($module['title'])->toStartWith('Участок')
        ->and($module['description_locale'])->toBe('ru')
        ->and($lessons[0]['title'])->toBe('Урок 1.1 — Определите продукт и найдите участок')
        ->and($lessons[0]['content_locale'])->toBe('ru')
        ->and($lessons[0]['content'])->toContain('Ley 12/2023')->toContain('CASA47')
        // Module 1 is translated in full, its tables included.
        ->and(collect($module['blocks'])->pluck('body_locale')->unique()->all())->toBe(['ru']);

    // Not yet (module 2): the title translated, the text the Spanish, with
    // its language said.
    $next = $russian['modules'][1];
    expect($next['lessons'][0]['title'])->toBe('Урок 2.1 — Счёт результатов девелопера (CRP)')
        ->and($next['lessons'][0]['content_locale'])->toBe('es')
        ->and($next['lessons'][0]['content'])->toBe(
            Module::query()->where('course_id', $course->id)->where('order', 2)->first()
                ->lessons()->orderBy('order')->first()->content_es,
        )
        ->and(collect($next['blocks'])->pluck('body_locale')->unique()->all())->toBe(['es']);

    expect(Module::query()->where('course_id', $course->id)->orderBy('order')->first()
        ->checklistItems()->orderBy('order')->first()->text_ru)
        ->toBe('Карточка продукта определена (тип, аудитория, типология квартир, уровень отделки, целевая цена)');

    // Spanish readers are untouched; Arabic is served as Arabic.
    $spanish = $learn('es');
    expect($spanish['modules'][0]['lessons'][0]['title'])->toBe('Lección 1.1 — Define el producto y encuentra el solar')
        ->and($spanish['modules'][0]['lessons'][0]['content_locale'])->toBe('es')
        ->and($learn('ar')['modules'][0]['lessons'][1]['content_locale'])->toBe('ar');
});

it('empties a translation that is taken back to Spanish on the next run', function () {
    $this->seed([ProvinceSeeder::class, CurriculumV2Seeder::class, ChecklistSeeder::class, CourseTranslationSeeder::class]);
    $lesson = Course::query()->firstOrFail()->modules()->orderBy('order')->first()->lessons()->orderBy('order')->first();
    expect($lesson->content_fr)->not->toBeNull();

    // A folder with no module files: every French piece reads the Spanish again.
    $root = storage_path('framework/testing/traducciones-'.uniqid());
    File::ensureDirectoryExists("{$root}/fr");
    config(['curso.translations' => $root]);

    try {
        $this->seed(CourseTranslationSeeder::class);
        expect($lesson->fresh()->content_fr)->toBeNull()
            ->and($lesson->fresh()->title_fr)->toBeNull()
            ->and($lesson->fresh()->content_es)->not->toBeNull();
    } finally {
        File::deleteDirectory($root);
    }
});

it('stops a deploy on a translation that lost a figure, before the site goes down', function () {
    $this->artisan('app:check-translations')->assertSuccessful();

    $root = storage_path('framework/testing/traducciones-'.uniqid());
    $spanish = CourseSource::fromConfig()->read('1_contenido/modulos/10_preentrega.md');
    File::ensureDirectoryExists("{$root}/ru/modulos");
    File::put("{$root}/ru/modulos/10_preentrega.md", str_replace('Ley 5/2019', 'Ley 5/2020', $spanish));
    config(['curso.translations' => $root]);

    try {
        $this->artisan('app:check-translations')
            ->expectsOutputToContain('5/2019')
            ->assertFailed();
    } finally {
        File::deleteDirectory($root);
    }
});
