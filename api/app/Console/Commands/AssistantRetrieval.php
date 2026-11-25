<?php

namespace App\Console\Commands;

use App\Models\AssistantChunk;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Province;
use App\Services\Assistant\Indexer;
use App\Services\Assistant\Scope;
use App\Services\Assistant\Tokenizer;
use App\Services\Assistant\Tools;
use Illuminate\Console\Command;

/**
 * The search, checked on its own: the questions of
 * resources/assistant/retrieval.json go through the same search an answer
 * starts with, and each must bring the piece of the course it names (a
 * lesson, a module's text, a template's card, the offices of the student's
 * province) among the documents the model would receive. No model is called
 * and nothing is spent, so it runs after every build of the index: a package,
 * a translation or a change to the search that loses a piece shows here
 * before a student asks.
 *
 * It passes when the share found reaches `assistant.retrieval.min_recall` and
 * every gate case passes (the ones that say what must NOT come: a module not
 * owned, another province, the extra's template).
 *
 *   php artisan assistant:retrieval            # run, exit 1 if it fails
 *   php artisan assistant:retrieval --case=m4-icio --show
 *   php artisan assistant:retrieval --locale=ru
 */
class AssistantRetrieval extends Command
{
    protected $signature = 'assistant:retrieval {--case=* : run only these cases} {--locale= : run only the cases asked in this language} {--show : print the documents found for each case} {--failed : print only the cases that fail}';

    protected $description = 'Check that the AI assistant\'s search brings the right pieces of the course (no model, no cost)';

    /** @var array<int, array{order: int, financials: bool}> module id => its place */
    private array $modules = [];

    /** @var array<int, string> lesson id => its number («1.3», «F.7») */
    private array $lessons = [];

    public function handle(Tools $tools): int
    {
        $set = json_decode((string) file_get_contents((string) config('assistant.retrieval.file')), true);
        $cases = collect($set['cases'] ?? []);
        if ($only = $this->option('case')) {
            $cases = $cases->whereIn('id', $only);
        }
        if ($locale = $this->option('locale')) {
            $cases = $cases->filter(fn (array $case) => ($case['locale'] ?? 'es') === $locale);
        }

        $course = Indexer::course();
        if ($course === null || (Indexer::state()['chunks'] ?? 0) === 0) {
            $this->error('No course index: run php artisan assistant:index first.');

            return self::FAILURE;
        }
        $this->outline($course);

        $found = 0;
        $gatesFailed = 0;
        $positive = 0;
        foreach ($cases as $case) {
            $locale = (string) ($case['locale'] ?? 'es');
            $docs = $tools->search((string) $case['q'], $locale, $this->scope($case, $course), []);
            $rows = AssistantChunk::query()->whereIn('id', array_column($docs, 'id'))->get()->keyBy('id');
            $pieces = array_map(fn (array $doc) => $this->describe($doc, $rows[$doc['id']] ?? null), $docs);

            $expect = (array) ($case['expect'] ?? []);
            $gate = array_intersect(array_keys($expect), ['none_of_modules', 'none_of_kinds', 'none_with_text']) !== [];
            [$ok, $note] = $this->check($expect, $pieces);
            if ($gate) {
                $gatesFailed += $ok ? 0 : 1;
            } else {
                $positive++;
                $found += $ok ? 1 : 0;
            }

            if (! $ok || ! $this->option('failed')) {
                $this->line(sprintf('%s %-26s %-3s %s', $ok ? '<info>✓</info>' : '<error>✗</error>', $case['id'], $locale, $note));
            }
            if ($this->option('show') || (! $ok && $this->option('failed'))) {
                foreach ($pieces as $i => $piece) {
                    $this->line(sprintf('      %d. %5.2f  %s | %s', $i + 1, $piece['score'], mb_strimwidth($piece['title'], 0, 62, '…'), mb_strimwidth((string) preg_replace('/\s+/u', ' ', $piece['text']), 0, 70, '…')));
                }
            }
        }

        $recall = $positive === 0 ? 1.0 : $found / $positive;
        $passed = $recall >= (float) config('assistant.retrieval.min_recall') && $gatesFailed === 0;
        $this->newLine();
        $this->line(sprintf('%d of %d pieces found (%.0f %%), %d gate case(s) failed, %d documents per question.', $found, $positive, $recall * 100, $gatesFailed, (int) config('assistant.retrieval.top_k')));
        $passed ? $this->info('Passed.') : $this->error('Not passed: the search loses pieces of the course.');

        return $passed || $this->option('case') || $this->option('locale') ? self::SUCCESS : self::FAILURE;
    }

    private function outline(Course $course): void
    {
        foreach ($course->modules()->get() as $module) {
            $this->modules[(int) $module->id] = ['order' => (int) $module->order, 'financials' => $module->isFinancials()];
        }
        foreach (Lesson::query()->whereIn('module_id', array_keys($this->modules))->get(['id', 'title_es']) as $lesson) {
            if (preg_match('/^Lección ([\dF]+\.\d+)/u', (string) $lesson->title_es, $m) === 1) {
                $this->lessons[(int) $lesson->id] = $m[1];
            }
        }
    }

    /** Who asks: «full» (the 12 modules), «modules:1,2», «financials» (the course and the extra); a province by its INE code. */
    private function scope(array $case, Course $course): Scope
    {
        $as = (string) ($case['as'] ?? 'full');
        $province = isset($case['province']) ? Province::query()->where('code', (int) $case['province'])->value('id') : null;
        $phases = array_keys(array_filter($this->modules, fn (array $m) => ! $m['financials']));
        if ($as === 'financials') {
            return Scope::make(false, true, true, array_keys($this->modules), $province === null ? null : (int) $province);
        }
        if ($as === 'full') {
            return Scope::make(false, true, true, $phases, $province === null ? null : (int) $province);
        }
        $orders = array_map('intval', explode(',', substr($as, strlen('modules:'))));
        $owned = array_keys(array_filter($this->modules, fn (array $m) => ! $m['financials'] && in_array($m['order'], $orders, true)));

        return Scope::make(false, true, false, $owned, $province === null ? null : (int) $province);
    }

    /**
     * @param  array<string, mixed>  $doc
     * @return array{kind: string, module: ?int, financials: bool, lesson: ?string, province: bool, title: string, text: string, score: float}
     */
    private function describe(array $doc, ?AssistantChunk $row): array
    {
        $module = $row?->module_id === null ? null : ($this->modules[(int) $row->module_id] ?? null);

        return [
            'kind' => (string) ($row?->source_type ?? ''),
            'module' => $module['order'] ?? null,
            'financials' => (bool) ($module['financials'] ?? false),
            'lesson' => $row?->lesson_id === null ? null : ($this->lessons[(int) $row->lesson_id] ?? null),
            'province' => $row?->province_id !== null,
            'title' => (string) $doc['title'],
            'text' => (string) $doc['text'],
            'score' => (float) ($doc['score'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $expect
     * @param  list<array<string, mixed>>  $pieces
     * @return array{0: bool, 1: string}
     */
    private function check(array $expect, array $pieces): array
    {
        $fold = fn (string $text) => mb_strtolower(Tokenizer::plain($text));

        foreach ((array) ($expect['none_of_modules'] ?? []) as $order) {
            foreach ($pieces as $piece) {
                if ($piece['module'] === (int) $order && ! $piece['financials']) {
                    return [false, "a piece of Module {$order} came: {$piece['title']}"];
                }
            }
        }
        foreach ((array) ($expect['none_of_kinds'] ?? []) as $kind) {
            [$type, $title] = array_pad(explode(':', (string) $kind, 2), 2, null);
            foreach ($pieces as $piece) {
                if ($piece['kind'] === $type && ($title === null || str_contains($fold($piece['title']), $fold($title)))) {
                    return [false, "a piece of kind {$kind} came: {$piece['title']}"];
                }
            }
        }
        foreach ((array) ($expect['none_with_text'] ?? []) as $text) {
            foreach ($pieces as $piece) {
                if (str_contains($fold($piece['title'].' '.$piece['text']), $fold((string) $text))) {
                    return [false, "a piece with «{$text}» came: {$piece['title']}"];
                }
            }
        }

        $wanted = array_intersect_key($expect, array_flip(['lesson', 'module', 'kind', 'title', 'text']));
        if ($wanted === []) {
            return [true, 'nothing that must not come'];
        }
        foreach ($pieces as $i => $piece) {
            if (isset($wanted['lesson']) && $piece['lesson'] !== (string) $wanted['lesson']) {
                continue;
            }
            if (isset($wanted['module']) && ($piece['module'] !== (int) $wanted['module'] || $piece['financials'])) {
                continue;
            }
            if (isset($wanted['kind']) && $piece['kind'] !== (string) $wanted['kind']) {
                continue;
            }
            if (isset($wanted['title']) && ! str_contains($fold($piece['title']), $fold((string) $wanted['title']))) {
                continue;
            }
            if (isset($wanted['text']) && ! str_contains($fold($piece['title'].' '.$piece['text']), $fold((string) $wanted['text']))) {
                continue;
            }

            return [true, 'document '.($i + 1)];
        }

        return [false, 'not among the '.count($pieces).' documents: '.json_encode($wanted, JSON_UNESCAPED_UNICODE)];
    }
}
