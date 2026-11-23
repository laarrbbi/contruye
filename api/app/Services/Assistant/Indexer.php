<?php

namespace App\Services\Assistant;

use App\Models\AssistantChunk;
use App\Models\ChecklistItem;
use App\Models\ContentBlock;
use App\Models\Course;
use App\Models\CourseDocument;
use App\Models\Module;
use App\Models\ProvinceGuide;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The course cut in pieces for the assistant, in every language the course
 * is written in, each piece with who may read it:
 * - a lesson's text: its module's owners (`module`; `financials` for the
 *   extra), anyone for a free lesson (`free`);
 * - a module's own texts (laws that apply, where to go, partners in your
 *   province) and its checklist: its owners;
 * - the course documents: as the player gates them (`enrolled` or `full`);
 * - each template (TemplateKnowledge: what it calculates, its inputs, its
 *   formulas, Nicolás's run of it), in Spanish: its module's owners;
 * - the province directory (Anexo A), each province's offices phase by
 *   phase: the owners of that phase's module, of that province only (Scope).
 * Never the quizzes or the final exam (their answers stay with QuizController),
 * never the owner's commercial strategy (it is not published).
 *
 * Read from the database, so it is the text the student reads, translations
 * included; rebuilt whole by `php artisan assistant:index`, which records its
 * version: the frequent-question cache and the evaluation gate follow it.
 */
final class Indexer
{
    private const SETTING = 'assistant.index';

    /** @return array{version: string, chunks: int, built_at: string} */
    public function __construct(private readonly TemplateKnowledge $templates) {}

    public function rebuild(): array
    {
        $course = self::course();
        $pieces = $course === null ? [] : $this->pieces($course);

        DB::transaction(function () use ($pieces) {
            DB::table('assistant_postings')->delete();
            AssistantChunk::query()->delete();

            foreach ($pieces as $piece) {
                $chunk = AssistantChunk::query()->create($piece + ['length' => count(Tokenizer::terms($piece['text']))]);
                $rows = [];
                foreach (Tokenizer::counts($piece['title'].' '.$piece['text']) as $term => $tf) {
                    $rows[] = ['locale' => $piece['locale'], 'term' => $term, 'chunk_id' => $chunk->id, 'tf' => min($tf, 65535)];
                }
                foreach (array_chunk($rows, 500) as $batch) {
                    DB::table('assistant_postings')->insert($batch);
                }
            }
        });

        $state = [
            'version' => substr(hash('sha256', json_encode(array_map(fn (array $p) => [$p['locale'], $p['access'], $p['module_id'], $p['province_id'], $p['title'], $p['text']], $pieces))), 0, 16),
            'chunks' => count($pieces),
            'built_at' => now()->toIso8601String(),
        ];
        Setting::setValue(self::SETTING, $state);

        return $state;
    }

    /** The index's version, or «none» before the first build. */
    public static function version(): string
    {
        return (string) (Setting::getValue(self::SETTING)['version'] ?? 'none');
    }

    /** @return array{version?: string, chunks?: int, built_at?: string} */
    public static function state(): array
    {
        return Setting::getValue(self::SETTING);
    }

    public static function course(): ?Course
    {
        return Course::query()->where('status', 'published')->orderBy('id')->first();
    }

    /** @return list<array<string, mixed>> */
    private function pieces(Course $course): array
    {
        $pieces = [];
        $locales = config('locales.supported');
        $modules = $course->modules()->with(['lessons' => fn ($q) => $q->orderBy('order')])->orderBy('order')->get();

        foreach ($modules as $module) {
            $access = $module->isFinancials() ? 'financials' : 'module';

            foreach ($module->lessons as $lesson) {
                foreach ($locales as $locale) {
                    $text = $locale === 'es' ? $lesson->content_es : $lesson->{'content_'.$locale};
                    if (blank($text)) {
                        continue;
                    }
                    $title = $this->moduleLabel($module, $locale).' · '.($lesson->{'title_'.$locale} ?: $lesson->title_es);
                    foreach ($this->split((string) $text) as $part) {
                        $pieces[] = $this->piece($locale, 'lesson', $lesson->id, $module->id, $lesson->id, $lesson->is_free_preview ? 'free' : $access, $title, "/learn/{$course->slug}/lessons/{$lesson->id}", $part);
                    }
                }
            }

            $blocks = ContentBlock::query()->where('module_id', $module->id)->whereNull('lesson_id')
                ->whereIn('type', ['overview', 'section'])->orderBy('order')->get();
            foreach ($blocks as $block) {
                foreach ($locales as $locale) {
                    $text = $block->{'body_'.$locale};
                    if (blank($text)) {
                        continue;
                    }
                    $heading = preg_match('/^#{1,3}\s+(.+)$/m', (string) $text, $m) ? trim($m[1]) : $this->moduleLabel($module, $locale);
                    foreach ($this->split((string) $text) as $part) {
                        $pieces[] = $this->piece($locale, 'module', $block->id, $module->id, null, $access, $this->moduleLabel($module, $locale).' · '.$heading, $this->modulePath($course, $module, (string) $block->body_es), $part);
                    }
                }
            }

            $items = ChecklistItem::query()->where('module_id', $module->id)->orderBy('order')->get();
            if ($items->isNotEmpty()) {
                foreach ($locales as $locale) {
                    $lines = $items->map(fn ($item) => $item->{'text_'.$locale})->filter()->map(fn ($text) => '- [ ] '.$text);
                    if ($lines->isEmpty()) {
                        continue;
                    }
                    foreach ($this->split($lines->implode("\n")) as $part) {
                        $pieces[] = $this->piece($locale, 'checklist', $module->id, $module->id, null, $access, $this->moduleLabel($module, $locale).' · Checklist', "/learn/{$course->slug}/modules/{$module->id}/tareas", $part);
                    }
                }
            }
        }

        $this->templatePieces($course, $modules, $pieces);
        $this->provincePieces($course, $modules, $locales, $pieces);

        foreach (CourseDocument::query()->where('course_id', $course->id)->orderBy('order')->get() as $document) {
            foreach ($locales as $locale) {
                $text = $document->{'body_'.$locale};
                if (blank($text)) {
                    continue;
                }
                $title = $document->{'title_'.$locale} ?: $document->title_es;
                foreach ($this->split((string) $text) as $part) {
                    $pieces[] = $this->piece($locale, 'document', $document->id, null, null, $document->access === 'full' ? 'full' : 'enrolled', $title, "/learn/{$course->slug}/docs/{$document->slug}", $part);
                }
            }
        }

        return $pieces;
    }

    /**
     * Each template of the package whose module the course has, as its module's owners read it: in Spanish.
     *
     * @param  Collection<int, Module>  $modules
     * @param  list<array<string, mixed>>  $pieces
     */
    private function templatePieces(Course $course, $modules, array &$pieces): void
    {
        $byOrder = $modules->reject->isFinancials()->keyBy('order');
        $financials = $modules->first->isFinancials();
        foreach ($this->templates->cards() as $index => $card) {
            $module = ($card['financials'] ?? false) ? $financials : $byOrder->get($card['module_order']);
            if ($module === null) {
                continue;
            }
            $title = $this->moduleLabel($module).' · '.$card['title'];
            $path = $module->isFinancials() ? "/learn/{$course->slug}/financials" : "/learn/{$course->slug}/modules/{$module->id}/plantilla";
            foreach ($this->split($card['text']) as $part) {
                $pieces[] = $this->piece('es', 'template', $index + 1, $module->id, null, 'module', $title, $path, $part);
            }
        }
    }

    private const WHERE = ['es' => 'dónde ir', 'en' => 'where to go', 'fr' => 'où s\'adresser', 'ar' => 'إلى أين تتوجّه', 'ru' => 'куда обращаться', 'uk' => 'куди звертатися'];

    /**
     * The province directory (Anexo A): for each province, the offices of each
     * phase, in every language its rows are translated into, for the owners of
     * that phase's module in that province.
     *
     * @param  Collection<int, Module>  $modules
     * @param  list<string>  $locales
     * @param  list<array<string, mixed>>  $pieces
     */
    private function provincePieces(Course $course, $modules, array $locales, array &$pieces): void
    {
        $byOrder = $modules->reject->isFinancials()->keyBy('order');
        foreach (ProvinceGuide::query()->orderBy('province_id')->get() as $guide) {
            foreach ($locales as $locale) {
                [$data, $language] = $guide->localizedData($locale);
                if ($language !== $locale) {
                    continue;
                }
                $name = trim((string) ($data['prov'] ?? '')).' ('.trim((string) ($data['ccaa'] ?? $guide->ccaa)).')';
                foreach ($byOrder as $order => $module) {
                    $rows = array_filter(array_map(fn ($row) => is_array($row) ? implode(': ', array_filter(array_map('trim', array_map('strval', $row)))) : '', $guide->phaseRows((int) $order, $data)));
                    if ($rows === []) {
                        continue;
                    }
                    $label = $this->moduleLabel($module, $locale);
                    $text = "**{$name} · {$label}, ".(self::WHERE[$locale] ?? self::WHERE['es'])."**\n".implode("\n", array_map(fn (string $row) => '- '.$row, $rows));
                    foreach ($this->split($text) as $part) {
                        $pieces[] = $this->piece($locale, 'province', $guide->id, $module->id, null, 'module', "{$label} · Anexo A · {$name}", "/learn/{$course->slug}/modules/{$module->id}/partners", $part, (int) $guide->province_id);
                    }
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function piece(string $locale, string $type, int $sourceId, ?int $moduleId, ?int $lessonId, string $access, string $title, ?string $path, string $text, ?int $provinceId = null): array
    {
        return [
            'locale' => $locale,
            'source_type' => $type,
            'source_id' => $sourceId,
            'module_id' => $moduleId,
            'lesson_id' => $lessonId,
            'province_id' => $provinceId,
            'access' => $access,
            'title' => mb_substr($title, 0, 255),
            'path' => $path,
            'text' => $text,
        ];
    }

    private const MODULE = ['es' => 'Módulo', 'en' => 'Module', 'fr' => 'Module', 'ar' => 'الوحدة', 'ru' => 'Модуль', 'uk' => 'Модуль'];

    private function moduleLabel(Module $module, string $locale = 'es'): string
    {
        return $module->isFinancials() ? 'Financials' : (self::MODULE[$locale] ?? 'Módulo').' '.$module->order;
    }

    /** The page of a module's own text: its laws, or its partners (where to go, the province). */
    private function modulePath(Course $course, Module $module, string $spanish): string
    {
        $section = str_contains(mb_strtolower(mb_substr($spanish, 0, 200)), 'leyes') ? 'leyes' : 'partners';

        return "/learn/{$course->slug}/modules/{$module->id}/{$section}";
    }

    /**
     * Paragraphs packed into pieces of about `retrieval.chunk_chars`, never
     * cutting a paragraph unless it is longer than that on its own (then at
     * its lines).
     *
     * @return list<string>
     */
    private function split(string $text): array
    {
        $limit = (int) config('assistant.retrieval.chunk_chars');
        $parts = [];
        $current = '';
        $paragraphs = preg_split('/\n\s*\n/', trim(str_replace("\r", '', $text))) ?: [];

        foreach ($paragraphs as $paragraph) {
            // A paragraph longer than a piece goes line by line; its lines stay one block (a list stays a list).
            $lines = mb_strlen($paragraph) > $limit ? (preg_split('/\n/', $paragraph) ?: [$paragraph]) : [$paragraph];
            foreach ($lines as $line => $piece) {
                if ($current !== '' && mb_strlen($current) + mb_strlen($piece) + 2 > $limit) {
                    $parts[] = $current;
                    $current = '';
                }
                $current = $current === '' ? $piece : $current.($line === 0 ? "\n\n" : "\n").$piece;
            }
        }
        if (trim($current) !== '') {
            $parts[] = $current;
        }

        return array_values(array_filter(array_map('trim', $parts), fn (string $p) => $p !== ''));
    }
}
