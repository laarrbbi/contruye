<?php

use App\Models\AssistantChunk;
use App\Models\ChecklistItem;
use App\Models\ContentBlock;
use App\Models\CourseDocument;
use App\Models\Lesson;
use App\Models\ProvinceGuide;
use App\Services\Assistant\Indexer;
use App\Services\Assistant\Llm;
use App\Services\Assistant\TemplateKnowledge;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\CourseTranslationSeeder;
use Database\Seeders\CurriculumV2Seeder;
use Database\Seeders\ProvinceGuideSeeder;
use Database\Seeders\ProvinceSeeder;
use Tests\Support\FakeLlm;

/*
 * The real course, indexed and searched. Every piece of it is in Nicolás's
 * index (lessons, module texts, checklists, documents, the offices of each
 * province phase by phase, the glossary, in the six languages; the templates
 * and the lessons' websites in Spanish), and the questions of the retrieval
 * set (resources/assistant/retrieval.json) bring the piece each one names.
 *
 * No model is called: this is the index and the search alone, on the content
 * students read. A new package, a translation or a change to the search that
 * loses a piece fails here, before a deploy.
 */

it('holds every piece of the real course in its index and finds what the retrieval set asks for', function () {
    $this->seed([ProvinceSeeder::class, CurriculumV2Seeder::class, ChecklistSeeder::class, ProvinceGuideSeeder::class, CourseTranslationSeeder::class]);
    app()->instance(Llm::class, $fake = new FakeLlm);
    $this->artisan('assistant:index')->assertSuccessful();

    $course = Indexer::course();
    $modules = $course->modules()->get();
    $phases = $modules->reject->isFinancials();
    $ids = $modules->pluck('id');
    $sources = fn (string $type, string $locale) => AssistantChunk::query()->where('source_type', $type)->where('locale', $locale)->distinct()->count('source_id');

    $lessons = Lesson::query()->whereIn('module_id', $ids)->count();
    $texts = ContentBlock::query()->whereIn('module_id', $ids)->whereNull('lesson_id')->whereIn('type', ['overview', 'section'])->count();
    $checklists = ChecklistItem::query()->whereIn('module_id', $ids)->distinct()->count('module_id');
    $documents = CourseDocument::query()->where('course_id', $course->id)->count();
    $provinces = ProvinceGuide::query()->count();
    $terms = count((array) config('glossary.terms'));
    expect($lessons)->toBeGreaterThanOrEqual(60)
        ->and($phases)->toHaveCount(12)
        ->and($provinces)->toBe(52)
        ->and($documents)->toBeGreaterThanOrEqual(4)
        ->and($terms)->toBeGreaterThanOrEqual(30);

    foreach (config('locales.supported') as $locale) {
        expect($sources('lesson', $locale))->toBe($lessons, "lessons in {$locale}")
            ->and($sources('module', $locale))->toBe($texts, "module texts in {$locale}")
            ->and($sources('checklist', $locale))->toBe($checklists, "checklists in {$locale}")
            ->and($sources('document', $locale))->toBe($documents, "course documents in {$locale}")
            ->and($sources('glossary', $locale))->toBe($terms, "glossary terms in {$locale}")
            // Anexo A: every province, for each of the twelve phases.
            ->and(AssistantChunk::query()->where('source_type', 'province')->where('locale', $locale)->distinct()->get(['province_id', 'module_id'])->count())
            ->toBe($provinces * $phases->count(), "province offices in {$locale}");
    }

    // In Spanish, as their owners read them: the templates' cards and the websites each lesson names.
    $resources = (array) config('lesson_resources.lessons');
    $withLinks = Lesson::query()->whereIn('module_id', $ids)->get(['title_es'])
        ->filter(fn (Lesson $lesson) => preg_match('/^Lección ([\dF]+\.\d+)/u', (string) $lesson->title_es, $m) === 1 && ($resources[$m[1]]['links'] ?? []) !== [])->count();
    expect($sources('template', 'es'))->toBe(count(app(TemplateKnowledge::class)->cards()))
        ->and($sources('links', 'es'))->toBe($withLinks)
        ->and($withLinks)->toBeGreaterThan(10)
        // Never the quizzes, the exam or anything else.
        ->and(AssistantChunk::query()->distinct()->pluck('source_type')->sort()->values()->all())
        ->toBe(['checklist', 'document', 'glossary', 'lesson', 'links', 'module', 'province', 'template']);

    $this->artisan('assistant:retrieval', ['--failed' => true])->expectsOutputToContain('Passed.')->assertSuccessful();
    expect($fake->requests)->toBeEmpty();
});
