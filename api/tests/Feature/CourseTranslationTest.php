<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\Province;
use App\Models\ProvinceGuide;
use App\Models\Quiz;
use App\Models\User;
use App\Support\Course\CourseSource;
use App\Support\Course\CourseTranslation;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\CourseTranslationSeeder;
use Database\Seeders\CurriculumV2Seeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProvinceGuideSeeder;
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
    expect(CourseTranslation::available())->toBe(['en', 'fr', 'ar', 'ru', 'uk']);

    foreach (CourseTranslation::available() as $locale) {
        $translation = CourseTranslation::fromConfig($locale);

        // Each call throws, naming file and section, at the first lost figure.
        expect($translation->modules())->toHaveCount(13)
            ->and($translation->course()['title'])->not->toBeEmpty();
        $translation->documents();
        $translation->assessments();
        expect($translation->provincePhrases())->not->toBeEmpty();
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

    // Not translated yet: the Spanish, with its language said. A copy of the
    // Russian folder with lesson 1.3 set back to <!-- es --> stands for any
    // piece still waiting for its translation, however far the files get.
    $root = storage_path('framework/testing/traducciones-'.uniqid());
    File::copyDirectory(config('curso.translations').'/ru', "{$root}/ru");
    $file = "{$root}/ru/modulos/01_solar_busqueda_analisis_urbanistico_due_diligence_y_adquisic.md";
    File::put($file, preg_replace('/^(## Урок 1\.3[^\n]*\n\n)(?:(?!## ).*\n)*/mu', "\$1<!-- es -->\n\n", File::get($file)));
    $realRoot = config('curso.translations');
    config(['curso.translations' => $root]);

    try {
        $this->seed(CourseTranslationSeeder::class);
        $pending = $learn('ru')['modules'][0]['lessons'][2];

        expect($pending['title'])->toStartWith('Урок 1.3')
            ->and($pending['content_locale'])->toBe('es')
            ->and($pending['content'])->toBe(
                Module::query()->where('course_id', $course->id)->orderBy('order')->first()
                    ->lessons()->orderBy('order')->skip(2)->first()->content_es,
            );
    } finally {
        File::deleteDirectory($root);
        config(['curso.translations' => $realRoot]);
    }

    expect(Module::query()->where('course_id', $course->id)->orderBy('order')->first()
        ->checklistItems()->orderBy('order')->first()->text_ru)
        ->toBe('Модель 1 · Покупка участка (входит в курс) заполнена: цена, автономное сообщество и фактические расходы');

    // Spanish readers are untouched; Arabic is served as Arabic, English as English.
    expect($learn('en')['course']['title'])->toBe('Open-market housing development in Spain')
        ->and($learn('en')['modules'][0]['lessons'][0]['title'])->toBe('Lesson 1.1 — Define the product and find the plot');
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

it('gives a Russian student the quiz in Russian, without the answers, and grades it on the Spanish key', function () {
    $this->seed([ProvinceSeeder::class, CurriculumV2Seeder::class, ChecklistSeeder::class, CourseTranslationSeeder::class]);
    $course = Course::query()->where('slug', 'promocion-inmobiliaria-espana')->firstOrFail();
    $quiz = Quiz::query()->where('module_id', $course->modules()->orderBy('order')->first()->id)->firstOrFail();
    $student = User::factory()->create(['locale' => 'ru']);
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'module_id' => null]);

    $served = $this->actingAs($student)->withHeader('Accept-Language', 'ru')
        ->getJson("/api/quizzes/{$quiz->id}")->assertOk();
    expect($served->json('data.title'))->toBe('Тест модуля 1 · 10 вопросов · минимум 7/10')
        ->and($served->json('data.questions.0.pregunta'))->toBe('Допустимая застройка выражается в:')
        ->and($served->json('data.questions.0.opciones.1'))->toBe(['letra' => 'B', 'texto' => 'm²t/m²s'])
        ->and($served->getContent())->not->toContain('correcta');

    // The right letters of the Spanish file, answered in a Russian session.
    $answers = collect($quiz->questions)->mapWithKeys(fn (array $question) => [
        (string) $question['numero'] => collect($question['opciones'])->firstWhere('correcta', true)['letra'],
    ])->all();

    $attemptId = $this->actingAs($student)->withHeader('Accept-Language', 'ru')
        ->postJson("/api/quizzes/{$quiz->id}/attempts")->assertCreated()->json('data.open_attempt.id');
    $result = $this->actingAs($student)->withHeader('Accept-Language', 'ru')
        ->postJson("/api/quiz-attempts/{$attemptId}/submit", ['answers' => $answers])->assertOk()->json('data');

    expect($result['score'])->toBe(count($answers))->and($result['passed'])->toBeTrue();
});

it('serves Anexo A\'s rows in the student\'s language, the names of institutions and laws as written', function () {
    $this->seed([ProvinceSeeder::class, CurriculumV2Seeder::class, ProvinceGuideSeeder::class, CourseTranslationSeeder::class]);
    $course = Course::query()->where('slug', 'promocion-inmobiliaria-espana')->firstOrFail();
    $student = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'module_id' => null]);
    $alicante = Province::query()->where('code', 3)->firstOrFail();

    $directory = fn (string $locale) => $this->actingAs($student)->withHeader('Accept-Language', $locale)
        ->getJson("/api/courses/{$course->slug}/directory/{$alicante->id}")->assertOk()->json('data');
    $english = $directory('en');
    $spanish = $directory('es');
    $plot = fn (array $data) => collect($data['phases'])->firstWhere('phase', 1)['rows'][0];

    expect($english['data_locale'])->toBe('en')
        ->and($plot($english)['name'])->toBe($plot($spanish)['name'])
        ->and($plot($english)['purpose'])->toBe('Nota simple (registry extract) and certificate of encumbrances (registradores.org)')
        ->and($plot($spanish)['purpose'])->toBe('Nota simple y certificación de cargas (registradores.org)')
        ->and($spanish['data_locale'])->toBe('es')
        // The planning law keeps its official name; the facts are translated, their laws still linked.
        ->and($english['urban_law'])->toBe($spanish['urban_law'])
        ->and($english['itp'])->toBe('9 % (11 % above 1 M€) · Ley 5/2025')
        ->and($english['itp_html'])->toContain('BOE-A-2025-11959');
});

it('refuses an Anexo A phrase file that leaves a phrase out or loses a figure', function () {
    $root = storage_path('framework/testing/traducciones-'.uniqid());
    File::copyDirectory(config('curso.translations').'/en', "{$root}/en");
    $file = "{$root}/en/datos/partners_por_provincia.json";
    $data = json_decode(File::get($file), true);
    unset($data['frases']['Alta de agua por vivienda']);
    $data['frases']['Gestión de RCD (RD 105/2008)'] = 'Construction waste management (RCD)';
    File::put($file, json_encode($data));

    try {
        expect(fn () => (new CourseTranslation(CourseSource::fromConfig(), "{$root}/en", 'en'))->provincePhrases())
            ->toThrow(RuntimeException::class, 'not translated: Alta de agua por vivienda');
        expect(fn () => (new CourseTranslation(CourseSource::fromConfig(), "{$root}/en", 'en'))->provincePhrases())
            ->toThrow(RuntimeException::class, 'the figure 105/2008 is missing');
    } finally {
        File::deleteDirectory($root);
    }
});

it('serves the course documents in the student\'s language, their laws linked as in the Spanish', function () {
    $this->seed([ProvinceSeeder::class, CurriculumV2Seeder::class, CourseTranslationSeeder::class]);
    $course = Course::query()->where('slug', 'promocion-inmobiliaria-espana')->firstOrFail();
    $student = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'module_id' => null]);
    $document = fn (string $slug, string $locale) => $this->actingAs($student)->withHeader('Accept-Language', $locale)
        ->getJson("/api/courses/{$course->slug}/documents/{$slug}")->assertOk()->json('data');

    foreach (['introduccion', 'recursos', 'anexo-a', 'anexo-b'] as $slug) {
        $spanish = $document($slug, 'es');
        foreach (['en', 'fr', 'ar', 'ru', 'uk'] as $locale) {
            $translated = $document($slug, $locale);
            expect($translated['body_locale'])->toBe($locale)
                ->and(substr_count($translated['body_html'], 'class="ley"'))->toBe(substr_count($spanish['body_html'], 'class="ley"'));
        }
    }

    // Anexo B's table reads the same phrases as each province's facts, its laws as written.
    expect($document('anexo-b', 'en')['body_html'])
        ->toContain('<td>First occupation licence (town hall)</td>')
        ->toContain('Ley 2/2016</a> del Suelo de Galicia');
});

it('translates the province rows on a fresh demo database, and drops them when the package changes the rows', function () {
    $this->seed(DatabaseSeeder::class);

    expect(ProvinceGuide::query()->count())->toBe(52)
        ->and(ProvinceGuide::query()->whereNull('data_en')->count())->toBe(0);

    // A new package changes a row: the old translation of it is not shown, the
    // Spanish is, until the translations are loaded again.
    $guide = ProvinceGuide::query()->firstOrFail();
    $data = $guide->data;
    $data['phases']['1'][0][1] = 'Otra cosa';
    $guide->update(['data' => $data]);
    $this->seed(ProvinceGuideSeeder::class);

    expect($guide->fresh()->data_en)->toBeNull()
        ->and(ProvinceGuide::query()->whereNull('data_en')->count())->toBe(1);
});
