<?php

namespace App\Services\Assistant;

use App\Models\AssistantChunk;
use App\Models\ChecklistItem;
use App\Models\ContentBlock;
use App\Models\Course;
use App\Models\CourseDocument;
use App\Models\Module;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * The course cut in pieces for the assistant, in every language the course
 * is written in, each piece with who may read it:
 * - a lesson's text: its module's owners (`module`; `financials` for the
 *   extra), anyone for a free lesson (`free`);
 * - a module's own texts (laws that apply, where to go, partners in your
 *   province) and its checklist: its owners;
 * - the course documents: as the player gates them (`enrolled` or `full`).
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
            'version' => substr(hash('sha256', json_encode(array_map(fn (array $p) => [$p['locale'], $p['access'], $p['module_id'], $p['title'], $p['text']], $pieces))), 0, 16),
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

    /** @return array<string, mixed> */
    private function piece(string $locale, string $type, int $sourceId, ?int $moduleId, ?int $lessonId, string $access, string $title, ?string $path, string $text): array
    {
        return [
            'locale' => $locale,
            'source_type' => $type,
            'source_id' => $sourceId,
            'module_id' => $moduleId,
            'lesson_id' => $lessonId,
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
            $pieces = mb_strlen($paragraph) > $limit ? (preg_split('/\n/', $paragraph) ?: [$paragraph]) : [$paragraph];
            foreach ($pieces as $piece) {
                if ($current !== '' && mb_strlen($current) + mb_strlen($piece) + 2 > $limit) {
                    $parts[] = $current;
                    $current = '';
                }
                $current = $current === '' ? $piece : $current."\n\n".$piece;
            }
        }
        if (trim($current) !== '') {
            $parts[] = $current;
        }

        return array_values(array_filter(array_map('trim', $parts), fn (string $p) => $p !== ''));
    }
}
