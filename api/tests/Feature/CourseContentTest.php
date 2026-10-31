<?php

use App\Models\ContentBlock;
use App\Models\Course;
use App\Models\CourseDocument;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\ProvinceGuide;
use App\Models\Quiz;
use App\Models\User;
use App\Support\ContentValidation;
use App\Support\Course\CourseSource;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\CurriculumV2Seeder;
use Database\Seeders\ProvinceGuideSeeder;
use Database\Seeders\ProvinceSeeder;

/*
 * The course is the instructor's package (curso/), integrated "tal como está,
 * sin reescribirlo". These tests hold the import to that: each module file is
 * rebuilt from what the database stores and compared line by line with the
 * file; the documents, quizzes, exam and province directory are compared with
 * their source.
 */

beforeEach(function () {
    $this->seed([ProvinceSeeder::class, CurriculumV2Seeder::class, ChecklistSeeder::class, ProvinceGuideSeeder::class]);
    $this->course = Course::query()->where('slug', 'promocion-inmobiliaria-espana')->firstOrFail();
    $this->source = CourseSource::fromConfig();
});

/** Non-blank lines, trailing spaces ignored: the comparison is about words, not spacing. */
function contentLines(string $text): array
{
    return array_values(array_filter(array_map('rtrim', explode("\n", $text)), fn (string $line) => trim($line) !== ''));
}

it('installs the 12 modules and Financials, with 51 lessons and 9', function () {
    $modules = $this->course->modules()->orderBy('order')->get();

    expect($modules)->toHaveCount(13)
        ->and($modules->where('kind', 'phase'))->toHaveCount(12)
        ->and($modules->last()->kind)->toBe('financials')
        ->and($modules->where('kind', 'phase')->sum(fn (Module $m) => $m->lessons()->count()))->toBe(51)
        ->and($modules->last()->lessons()->count())->toBe(9);

    // The module titles of the instructor's own summary page.
    expect($modules[0]->title_es)->toBe('Solar: búsqueda, análisis urbanístico, due diligence y adquisición')
        ->and($modules[11]->title_es)->toBe('Postventa y cierre de la promoción')
        ->and($modules[12]->title_es)->toBe('Financials — la plantilla financiera de la promoción')
        ->and($modules[0]->lessons()->orderBy('order')->first()->title_es)->toBe('Lección 1.1 — Define el producto y encuentra el solar');

    expect($this->course->title_es)->toBe('Promoción de viviendas de renta libre en España');
});

it('stores every module file without dropping or changing a line', function () {
    $modules = $this->course->modules()->orderBy('order')->with(['lessons', 'contentBlocks', 'checklistItems'])->get()->values();

    foreach ($this->source->modules() as $index => $parsed) {
        /** @var Module $module */
        $module = $modules[$index];
        $file = $this->source->read('1_contenido/'.$parsed['file']);

        // The title, back in the capitals of the file's heading.
        $heading = $module->isFinancials() ? mb_strtoupper($module->title_es) : 'MÓDULO '.$module->order.' — '.mb_strtoupper($module->title_es);

        $rebuilt = ['# '.$heading];
        foreach (explode("\n", $module->description_es) as $line) {
            $rebuilt[] = '> '.$line;
        }
        $blocks = $module->contentBlocks->sortBy('order');
        foreach ($blocks->where('type', 'overview') as $block) {
            $rebuilt[] = $block->body_es;
        }
        foreach ($module->lessons->sortBy('order') as $lesson) {
            $rebuilt[] = '## '.$lesson->title_es;
            $rebuilt[] = $lesson->content_es;
        }
        $rebuilt[] = '## '.$parsed['checklist_heading'];
        foreach ($module->checklistItems->sortBy('order') as $item) {
            $rebuilt[] = '- [ ] '.$item->text_es;
        }
        foreach ($blocks->where('type', 'section') as $block) {
            $rebuilt[] = $block->body_es;
        }

        expect(contentLines(implode("\n", $rebuilt)))->toBe(contentLines($file), $parsed['file']);
    }
});

it('keeps the introduction, the resources and the annexes as written', function () {
    $documents = CourseDocument::query()->where('course_id', $this->course->id)->orderBy('order')->get()->keyBy('slug');

    expect($documents->keys()->all())->toBe(['introduccion', 'recursos', 'anexo-a', 'anexo-b']);

    foreach (['introduccion' => '00_introduccion.md', 'recursos' => '14_recursos_descargables.md', 'anexo-b' => '17_anexo_b_fiscalidad_por_ccaa.md'] as $slug => $file) {
        expect(contentLines($documents[$slug]->body_es))->toBe(contentLines($this->source->read('1_contenido/modulos/'.$file)), $file);
    }

    // Anexo A: its preface here, and the 52 provinces in the directory —
    // together, the whole file.
    $annex = $this->source->read('1_contenido/modulos/16_anexo_a_partners_por_provincia.md');
    $pieces = [$documents['anexo-a']->body_es];
    $lastCcaa = null;
    foreach (ProvinceGuide::query()->orderBy('id')->get() as $guide) {
        if ($guide->ccaa !== $lastCcaa) {
            $pieces[] = '---';
            $pieces[] = '## '.$guide->ccaa;
            $lastCcaa = $guide->ccaa;
        }
        $pieces[] = $guide->section_es;
    }
    expect(contentLines(implode("\n", $pieces)))->toBe(contentLines($annex));

    // The commercial strategy is the owner's, not the students'.
    expect($documents->has('estrategia-comercial'))->toBeFalse();
});

it('shows a province exactly the rows the annex gives it, phase by phase', function () {
    $phaseNames = [];

    foreach (ProvinceGuide::query()->get() as $guide) {
        // The table under the province in the annex: "| 1 · Solar | name | what for |",
        // then "|  | name | what for |" for the following rows of the phase.
        $rows = [];
        $phase = null;
        foreach (explode("\n", $guide->section_es) as $line) {
            if (preg_match('/^\| (\d+ · [^|]+?|) \| (.+?) \| (.+?) \|$/u', $line, $m) !== 1 || $m[2] === 'Partner / institución') {
                continue;
            }
            if ($m[1] !== '') {
                $phase = (int) $m[1];
                $phaseNames[$phase] = $m[1];
            }
            $rows[$phase][] = [$m[2], $m[3]];
        }

        foreach (range(1, 12) as $n) {
            expect($guide->phaseRows($n))->toBe($rows[$n] ?? [], "{$guide->data['prov']}, fase {$n}");
        }
    }

    expect($phaseNames)->toHaveCount(12);
});

it('installs the 13 quizzes and the final exam exactly as delivered', function () {
    $modules = $this->course->modules()->orderBy('order')->with('quiz')->get();
    $source = $this->source->quizzes();

    foreach ($this->source->modules() as $index => $parsed) {
        $quiz = $modules[$index]->quiz;
        expect($quiz)->not->toBeNull()
            ->and($quiz->title_es)->toBe($source[$parsed['section']]['titulo'])
            // toEqual: MySQL stores JSON objects with their keys reordered.
            ->and($quiz->questions)->toEqual($source[$parsed['section']]['preguntas']);
    }

    expect($modules[0]->quiz->pass_mark)->toBe(7)
        ->and($modules[9]->quiz->pass_mark)->toBe(6)   // module 10: 6 of 8
        ->and($modules[12]->quiz->pass_mark)->toBe(6);  // Financials: 6 of 8

    $exam = Quiz::query()->where('kind', 'final')->sole();
    expect($exam->module_id)->toBeNull()
        ->and($exam->questionCount())->toBe(40)
        ->and($exam->pass_mark)->toBe(32)
        ->and($exam->duration_minutes)->toBe(60)
        ->and($exam->questions)->toEqual($this->source->finalExam()['preguntas']);

    // Every question has exactly one right answer: grading depends on it.
    foreach (Quiz::all() as $quiz) {
        expect($quiz->answerKey())->toHaveCount($quiz->questionCount());
    }
});

it('serves the texts rendered, to owners, without the editorial flags', function () {
    $student = User::factory()->create();
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $this->course->id, 'module_id' => null]);

    $data = $this->actingAs($student)->getJson("/api/courses/{$this->course->slug}/learn")->assertOk()->json('data');
    $module1 = collect($data['modules'])->firstWhere('order', 1);

    expect($module1['kind'])->toBe('phase')
        ->and($module1['description_html'])->toContain('<strong>Objetivo:</strong>')
        // "Leyes que aplican" and "Dónde ir", with their tables.
        ->and(collect($module1['blocks'])->where('type', 'overview'))->toHaveCount(2)
        ->and(collect($module1['blocks'])->firstWhere('type', 'overview')['body_html'])->toContain('<table>')
        ->and($module1['lessons'][0]['content_html'])->toContain('<li>')
        ->and($module1['quiz']['questions_count'])->toBe(10)
        ->and(json_encode($data))->not->toContain('needs_validation')
        // The answers of the quizzes are never in the player's payload.
        ->and(json_encode($data))->not->toContain('correcta');

    expect(collect($data['modules'])->last()['kind'])->toBe('financials')
        ->and($data['course']['final_exam']['questions_count'])->toBe(40)
        ->and(collect($data['course']['documents'])->pluck('slug')->all())->toBe(['introduccion', 'recursos', 'anexo-a', 'anexo-b']);
});

it('opens the first two lessons of module 1 to everyone, and nothing else', function () {
    $previews = Lesson::query()->where('is_free_preview', true)->with('module')->get();

    expect($previews)->toHaveCount(2)
        ->and($previews->pluck('module.order')->unique()->all())->toBe([1])
        ->and($previews->pluck('order')->sort()->values()->all())->toBe([1, 2]);
});

it('still flags passages awaiting validation, and finds none in the delivered text', function () {
    // The marker helper drives both the admin badge and the review report.
    expect(ContentValidation::flags('Plazo habitual (pendiente de validar con el formador).'))->toBeTrue()
        ->and(ContentValidation::flags('El promotor solicita la licencia de obras.'))->toBeFalse();

    expect(ContentBlock::query()->where('needs_validation', true)->count())->toBe(0);
});

it('leaves the English translation pending but never blank on screen', function () {
    $module = Module::query()->where('order', 1)->firstOrFail();

    // Keys exist and are empty — the human translation pass has not run.
    expect($module->title_en)->toBeNull();

    // …but the UI still shows the Spanish text instead of an empty string.
    app()->setLocale('en');
    expect($module->localizedTitle())->toBe($module->title_es)
        ->and($module->localizedDescription())->toBe($module->description_es);
    app()->setLocale('es');
});

it('does not rebuild an installed course, whose modules orders may reference', function () {
    $ids = $this->course->modules()->pluck('id')->all();

    $this->seed(CurriculumV2Seeder::class);

    expect($this->course->modules()->pluck('id')->all())->toBe($ids)
        ->and(Quiz::query()->count())->toBe(14);
});
