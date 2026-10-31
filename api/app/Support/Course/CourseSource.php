<?php

namespace App\Support\Course;

use RuntimeException;

/**
 * Reads the instructor's course package (config('curso.path')) and splits it
 * into what the platform stores, WITHOUT rewriting a word.
 *
 * A module file is cut at its "## " headings, and every piece keeps its text:
 * - the blockquote under the "# " title is the module description;
 * - the sections before the first lesson ("Leyes que aplican", "Dónde ir",
 *   Financials' "Mapa de la plantilla"…) are `overview` blocks, heading
 *   included;
 * - each "## Lección …" is a lesson, the heading is its title and the body its
 *   Markdown content;
 * - "## Checklist …" becomes the module's checklist items;
 * - what follows ("Partners en tu provincia") is a closing `section` block.
 *
 * The only change of form: the module title is the "# " heading's text in
 * sentence case, as the instructor's own summary page writes it ("Solar:
 * búsqueda, análisis urbanístico…") rather than in capitals.
 * CourseContentTest rebuilds every file from these pieces to prove nothing
 * was dropped or altered.
 */
final class CourseSource
{
    public function __construct(private readonly string $root) {}

    public static function fromConfig(): self
    {
        return new self(rtrim((string) config('curso.path'), '/'));
    }

    public function path(string $relative): string
    {
        return $this->root.'/'.ltrim($relative, '/');
    }

    public function exists(): bool
    {
        return is_file($this->path('1_contenido/indice.json'));
    }

    public function read(string $relative): string
    {
        $file = $this->path($relative);

        if (! is_file($file)) {
            throw new RuntimeException("Course package file missing: {$file}");
        }

        return str_replace("\r\n", "\n", (string) file_get_contents($file));
    }

    /**
     * @return mixed
     */
    public function json(string $relative)
    {
        return json_decode($this->read($relative), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * The index of the package: file and title of every section, in order.
     *
     * @return list<array{archivo: string, titulo: string}>
     */
    public function index(): array
    {
        return $this->json('1_contenido/indice.json');
    }

    /**
     * The 12 modules and Financials, parsed, in the order of the index.
     *
     * @return list<array<string, mixed>>
     */
    public function modules(): array
    {
        $modules = [];

        foreach ($this->index() as $entry) {
            if (preg_match('#^modulos/(\d{2})_#', $entry['archivo'], $m) !== 1) {
                continue;
            }
            $number = (int) $m[1];
            // 01–12 are the phases, 13 is Financials; the rest are documents.
            if ($number < 1 || $number > 13) {
                continue;
            }

            $parsed = self::parseModule($this->read('1_contenido/'.$entry['archivo']));
            $parsed['file'] = $entry['archivo'];
            $parsed['section'] = basename($entry['archivo'], '.md');
            $modules[] = $parsed;
        }

        return $modules;
    }

    /**
     * @return array{
     *   heading: string, title: string, kind: 'phase'|'financials', number: ?int,
     *   description: string,
     *   overview: list<string>, lessons: list<array{title: string, content: string}>,
     *   checklist_heading: ?string, checklist: list<string>, closing: list<string>
     * }
     */
    public static function parseModule(string $markdown): array
    {
        $lines = explode("\n", rtrim(str_replace("\r\n", "\n", $markdown), "\n"));

        if (! str_starts_with($lines[0] ?? '', '# ')) {
            throw new RuntimeException('A module file must start with its "# " title.');
        }

        $heading = substr($lines[0], 2);
        if (preg_match('/^MÓDULO (\d+) — (.+)$/u', $heading, $m) === 1) {
            $kind = 'phase';
            $number = (int) $m[1];
            $title = self::sentenceCase($m[2]);
        } elseif (str_starts_with($heading, 'FINANCIALS')) {
            $kind = 'financials';
            $number = null;
            $title = self::sentenceCase($heading);
        } else {
            throw new RuntimeException("Unrecognised module title: {$heading}");
        }

        // Preamble: the blockquote between the title and the first section.
        $description = [];
        $i = 1;
        for (; $i < count($lines) && ! str_starts_with($lines[$i], '## '); $i++) {
            if (trim($lines[$i]) === '') {
                continue;
            }
            if (! str_starts_with($lines[$i], '> ')) {
                throw new RuntimeException("Unexpected line before the first section: {$lines[$i]}");
            }
            $description[] = substr($lines[$i], 2);
        }

        // Sections, each from its "## " heading to the next one.
        $sections = [];
        for (; $i < count($lines); $i++) {
            if (str_starts_with($lines[$i], '## ')) {
                $sections[] = ['heading' => substr($lines[$i], 3), 'body' => []];

                continue;
            }
            $sections[count($sections) - 1]['body'][] = $lines[$i];
        }

        $overview = $lessons = $checklist = $closing = [];
        $checklistHeading = null;

        foreach ($sections as $section) {
            $body = trim(implode("\n", $section['body']), "\n");

            if (str_starts_with($section['heading'], 'Lección ')) {
                $lessons[] = ['title' => $section['heading'], 'content' => $body];
            } elseif (str_starts_with($section['heading'], 'Checklist')) {
                $checklistHeading = $section['heading'];
                foreach (explode("\n", $body) as $line) {
                    if (trim($line) === '') {
                        continue;
                    }
                    if (preg_match('/^- \[ \] (.+)$/u', $line, $item) !== 1) {
                        throw new RuntimeException("Not a checklist item: {$line}");
                    }
                    $checklist[] = $item[1];
                }
            } elseif ($lessons === []) {
                $overview[] = '## '.$section['heading']."\n\n".$body;
            } else {
                $closing[] = '## '.$section['heading']."\n\n".$body;
            }
        }

        return [
            'heading' => $heading,
            'title' => $title,
            'kind' => $kind,
            'number' => $number,
            'description' => implode("\n", $description),
            'overview' => $overview,
            'lessons' => $lessons,
            'checklist_heading' => $checklistHeading,
            'checklist' => $checklist,
            'closing' => $closing,
        ];
    }

    /**
     * The course-level documents shown to students, keyed by slug.
     *
     * The commercial strategy (15) is the owner's, not the student's: the
     * package's own student edition (curso_sin_evaluacion) leaves it out too.
     *
     * @return array<string, array{file: string, title: string, access: 'enrolled'|'full', body: string}>
     */
    public function documents(): array
    {
        $files = [
            'introduccion' => ['modulos/00_introduccion.md', 'Introducción', 'enrolled'],
            'recursos' => ['modulos/14_recursos_descargables.md', 'Recursos descargables', 'enrolled'],
            'anexo-a' => ['modulos/16_anexo_a_partners_por_provincia.md', 'Anexo A — Directorio de partners por provincia y por fase', 'full'],
            'anexo-b' => ['modulos/17_anexo_b_fiscalidad_por_ccaa.md', 'Anexo B — Fiscalidad y urbanismo por comunidad autónoma', 'enrolled'],
        ];

        $documents = [];
        foreach ($files as $slug => [$file, $title, $access]) {
            $body = rtrim($this->read('1_contenido/'.$file), "\n");

            // Anexo A: the preface and the official search engines. The 52
            // provinces after it are served from the directory, one province
            // at a time, with the same text (ProvinceGuide).
            if ($slug === 'anexo-a') {
                $body = rtrim(explode("\n---\n", $body, 2)[0], "\n");
            }

            $documents[$slug] = ['file' => $file, 'title' => $title, 'access' => $access, 'body' => $body];
        }

        return $documents;
    }

    /**
     * Anexo A, one entry per province: the section as written in the annex
     * ("### Almería · capital: Almería" down to its table) and the structured
     * rows from datos/partners_por_provincia.json, phase by phase.
     *
     * @return list<array{name: string, ccaa: string, section: string, data: array<string, mixed>}>
     */
    public function provinceGuides(): array
    {
        $annex = $this->read('1_contenido/modulos/16_anexo_a_partners_por_provincia.md');

        // Sections by "### " heading; each ends at the next heading or rule.
        preg_match_all('/^### (.+?) · capital: .+?$.*?(?=^### |^---$|^## |\z)/msu', $annex, $matches, PREG_SET_ORDER);
        $sections = [];
        foreach ($matches as $match) {
            $sections[$match[1]] = rtrim($match[0], "\n");
        }

        $guides = [];
        foreach ($this->json('1_contenido/datos/partners_por_provincia.json') as $province) {
            if (! isset($sections[$province['prov']])) {
                throw new RuntimeException("Anexo A has no section for {$province['prov']}");
            }
            $guides[] = [
                'name' => $province['prov'],
                'ccaa' => $province['ccaa'],
                'section' => $sections[$province['prov']],
                'data' => $province,
            ];
        }

        return $guides;
    }

    /**
     * The module quizzes, keyed by the module file's name (their `seccion`).
     *
     * @return array<string, array<string, mixed>>
     */
    public function quizzes(): array
    {
        $quizzes = [];
        foreach ($this->json('1_contenido/evaluacion/quizzes_por_modulo.json') as $quiz) {
            $quizzes[$quiz['seccion']] = $quiz;
        }

        return $quizzes;
    }

    /**
     * @return array<string, mixed>
     */
    public function finalExam(): array
    {
        return $this->json('1_contenido/evaluacion/examen_final.json');
    }

    /**
     * "SOLAR: BÚSQUEDA, ANÁLISIS URBANÍSTICO" → "Solar: búsqueda, análisis urbanístico".
     */
    public static function sentenceCase(string $text): string
    {
        $lower = mb_strtolower($text);

        return mb_strtoupper(mb_substr($lower, 0, 1)).mb_substr($lower, 1);
    }

    /**
     * "7/10" → 7. The pass mark of a quiz as the package states it.
     */
    public static function passMark(string $minimum): int
    {
        if (preg_match('#^(\d+)/(\d+)#', $minimum, $m) !== 1) {
            throw new RuntimeException("Unreadable pass mark: {$minimum}");
        }

        return (int) $m[1];
    }
}
