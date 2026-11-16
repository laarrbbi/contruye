<?php

namespace App\Support\Course;

use App\Models\ContentBlock;
use App\Models\Course;
use App\Models\CourseDocument;
use App\Models\Module;

/**
 * Rows of the course's own tables, read from the stored course text: the
 * introduction's «Las 12 fases» (each phase's short name and what comes out
 * of it) and a module's «Dónde ir» (institution, what you ask for, what they
 * give you, deadline and cost). Cells come back as written, in Markdown;
 * nothing is summarised or reworded.
 */
final class CourseTables
{
    /**
     * «Las 12 fases», by phase number, in the reader's language when the
     * introduction is translated (Spanish otherwise).
     *
     * @return array<int, array{name: string, outcome: string}>
     */
    public static function phases(Course $course, ?string $locale = null): array
    {
        $document = CourseDocument::query()->where('course_id', $course->id)->where('slug', 'introduccion')->first();
        if ($document === null) {
            return [];
        }

        $phases = [];
        foreach (self::rows($document->localizedBody($locale)) as $cells) {
            if (count($cells) === 3 && preg_match('/^\d{1,2}$/', $cells[0]) === 1 && ! isset($phases[(int) $cells[0]])) {
                $phases[(int) $cells[0]] = ['name' => $cells[1], 'outcome' => $cells[2]];
            }
        }

        return $phases;
    }

    /**
     * The data rows of a module's «Dónde ir» table, numbered from 1 as they
     * stand in the module (the numbers config/course_world.php refers to).
     *
     * @return array<int, array{institution: string, ask: string, get: string, plazo: string}>
     */
    public static function whereToGo(Module $module, ?string $locale = 'es'): array
    {
        $block = ContentBlock::query()
            ->where('module_id', $module->id)
            ->whereNull('lesson_id')
            ->where('type', 'overview')
            ->where('body_es', 'like', '## Dónde ir%')
            ->first();
        if ($block === null) {
            return [];
        }

        $rows = [];
        foreach (self::rows($block->localizedBody($locale)) as $cells) {
            if (count($cells) === 4) {
                $rows[count($rows) + 1] = ['institution' => $cells[0], 'ask' => $cells[1], 'get' => $cells[2], 'plazo' => $cells[3]];
            }
        }

        return $rows;
    }

    /**
     * A Markdown text's table rows, each as its trimmed cells; the header row
     * (the one above a |---| line) and the separators are left out.
     *
     * @return list<list<string>>
     */
    private static function rows(?string $markdown): array
    {
        $lines = preg_split('/\R/u', (string) $markdown) ?: [];
        $rows = [];
        foreach ($lines as $i => $line) {
            $line = trim($line);
            if (! str_starts_with($line, '|') || ! str_ends_with($line, '|')) {
                continue;
            }
            if (preg_match('/^\|[\s:|-]+\|$/', $line) === 1) {
                continue;
            }
            $next = trim($lines[$i + 1] ?? '');
            if (preg_match('/^\|[\s:|-]+\|$/', $next) === 1) {
                continue; // a header row
            }
            $cells = explode('|', str_replace('\\|', "\u{E000}", trim($line, '|')));
            $rows[] = array_map(fn (string $cell) => str_replace("\u{E000}", '|', trim($cell)), $cells);
        }

        return $rows;
    }
}
