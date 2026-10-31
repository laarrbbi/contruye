<?php

namespace App\Support\Course;

use RuntimeException;

/**
 * A translation of the instructor's package (config('curso.translations'),
 * one folder per locale: traducciones/ru/…), read against the Spanish it
 * translates. The Spanish in curso/ stays the reference; nothing here edits it.
 *
 * A translated module file mirrors the Spanish file section for section:
 *
 *   # <module title, as displayed>
 *   > <the objective blockquote, translated>
 *   ## <one "## " section per Spanish section, in the same order>
 *
 * A body that is only `<!-- es -->` is not translated yet: that piece keeps
 * reading the Spanish (the column stays empty, HasLocalizedAttributes falls
 * back). So a language can ship its titles and checklists before its lessons.
 *
 * Every translated piece is checked against its Spanish original, and a
 * mismatch stops the import with the file and section named:
 * - the same sections, lessons under their own numbers ("10.1"), the same
 *   number of checklist items and of table rows and cells;
 * - every number of the Spanish (rates, deadlines, thresholds, law and
 *   article numbers: "Ley 5/2019", "art. 17", "10 días") and every acronym of
 *   three letters or more (LOE, IVA, TRLGDCU) is still there. A legal figure
 *   is quoted, never reworded, in any language.
 */
final class CourseTranslation
{
    public const UNTRANSLATED = '<!-- es -->';

    /**
     * Upper-case Spanish words the instructor uses for emphasis or as table
     * labels: translated like any other word, so not checked as acronyms.
     */
    private const SPANISH_CAPS = [
        'ANTES', 'BENEFICIO', 'CONSTRUCCI', 'FINANCIALS', 'IMPUESTOS', 'INGRESOS', 'LISTA', 'NETO',
    ];

    public function __construct(
        private readonly CourseSource $source,
        private readonly string $root,
        public readonly string $locale,
    ) {}

    public static function fromConfig(string $locale): self
    {
        return new self(CourseSource::fromConfig(), rtrim((string) config('curso.translations'), '/').'/'.$locale, $locale);
    }

    /**
     * The supported locales that have a translation folder, Spanish excepted.
     *
     * @return list<string>
     */
    public static function available(): array
    {
        $root = rtrim((string) config('curso.translations'), '/');

        return array_values(array_filter(
            config('locales.supported'),
            fn (string $locale) => $locale !== config('locales.default') && is_dir("{$root}/{$locale}"),
        ));
    }

    public function path(string $relative): string
    {
        return $this->root.'/'.ltrim($relative, '/');
    }

    /**
     * curso.json: the course's title and one-paragraph description, and the
     * titles of the course documents, keyed by their slug.
     *
     * @return array{title: ?string, description: ?string, documents: array<string, string>}
     */
    public function course(): array
    {
        $file = $this->path('curso.json');
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR) : [];

        return [
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'documents' => $data['documents'] ?? [],
        ];
    }

    /**
     * The translated body of each course document whose file exists, keyed
     * by slug, checked against the Spanish body.
     *
     * @return array<string, string>
     */
    public function documents(): array
    {
        $bodies = [];
        foreach ($this->source->documents() as $slug => $document) {
            $file = $this->path($document['file']);
            if (! is_file($file)) {
                continue;
            }
            $body = rtrim(str_replace("\r\n", "\n", (string) file_get_contents($file)), "\n");
            if ($slug === 'anexo-a') {
                $body = rtrim(explode("\n---\n", $body, 2)[0], "\n");
            }
            self::assertSame("{$this->locale}/{$document['file']}", [
                ...self::differences($document['body'], $body, 'body'),
            ]);
            $bodies[$slug] = $body;
        }

        return $bodies;
    }

    /**
     * Each translated module, keyed by the index of its Spanish module (the
     * order of the curriculum). A module with no file is not in the list.
     *
     * @return array<int, array{
     *   title: string, description: ?string,
     *   overview: list<?string>, lessons: list<array{title: string, content: ?string}>,
     *   checklist: ?list<string>, closing: list<?string>
     * }>
     */
    public function modules(): array
    {
        $modules = [];

        foreach ($this->source->modules() as $index => $spanish) {
            $file = $this->path($spanish['file']);
            if (! is_file($file)) {
                continue;
            }

            $modules[$index] = self::parseModule(
                str_replace("\r\n", "\n", (string) file_get_contents($file)),
                $this->source->read('1_contenido/'.$spanish['file']),
                "{$this->locale}/{$spanish['file']}",
            );
        }

        return $modules;
    }

    /**
     * The module quizzes (keyed by `seccion`) and the final exam, where a
     * translated file exists. Each question keeps its number, its letters and
     * its right answer: only the words change.
     *
     * @return array{quizzes: array<string, array<string, mixed>>, exam: ?array<string, mixed>}
     */
    public function assessments(): array
    {
        $quizzes = [];
        $file = $this->path('evaluacion/quizzes_por_modulo.json');
        if (is_file($file)) {
            $spanish = $this->source->quizzes();
            foreach (json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR) as $quiz) {
                $original = $spanish[$quiz['seccion'] ?? ''] ?? null;
                if ($original === null) {
                    throw new RuntimeException("{$this->locale}: quiz for an unknown module: ".($quiz['seccion'] ?? '?'));
                }
                self::assertSame("{$this->locale}/quiz {$quiz['seccion']}", self::quizDifferences($original, $quiz));
                $quizzes[$quiz['seccion']] = $quiz;
            }
        }

        $exam = null;
        $file = $this->path('evaluacion/examen_final.json');
        if (is_file($file)) {
            $exam = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame("{$this->locale}/examen_final.json", self::quizDifferences($this->source->finalExam(), $exam));
        }

        return ['quizzes' => $quizzes, 'exam' => $exam];
    }

    /**
     * @return array{
     *   title: string, description: ?string,
     *   overview: list<?string>, lessons: list<array{title: string, content: ?string}>,
     *   checklist: ?list<string>, closing: list<?string>
     * }
     */
    public static function parseModule(string $translated, string $spanish, string $name): array
    {
        $original = self::split($spanish);
        $copy = self::split($translated);
        $errors = [];

        if (count($copy['sections']) !== count($original['sections'])) {
            throw new RuntimeException(sprintf(
                '%s: %d "## " sections, the Spanish has %d.',
                $name, count($copy['sections']), count($original['sections']),
            ));
        }

        $description = null;
        if (! self::untranslated($copy['preamble'])) {
            $lines = array_values(array_filter(explode("\n", $copy['preamble']), fn (string $line) => trim($line) !== ''));
            foreach ($lines as $line) {
                if (! str_starts_with($line, '> ')) {
                    $errors[] = "the objective must stay a \"> \" blockquote: {$line}";
                }
            }
            $description = implode("\n", array_map(fn (string $line) => substr($line, 2), $lines));
            $errors = [...$errors, ...self::differences($original['preamble'], $copy['preamble'], 'objective')];
        }

        $overview = $lessons = $closing = [];
        $checklist = null;

        foreach ($original['sections'] as $i => $section) {
            [$heading, $body] = [$copy['sections'][$i]['heading'], $copy['sections'][$i]['body']];
            $label = 'section '.($i + 1)." («{$section['heading']}»)";
            $translatedBody = self::untranslated($body) ? null : $body;

            if ($translatedBody !== null) {
                $errors = [...$errors, ...self::differences($section['body'], $translatedBody, $label)];
            }

            if (str_starts_with($section['heading'], 'Lección ')) {
                preg_match('/(?:\d+|F)\.\d+/', $section['heading'], $number);
                if ($number !== [] && ! str_contains($heading, $number[0])) {
                    $errors[] = "{$label}: the lesson heading must keep its number {$number[0]}: {$heading}";
                }
                $lessons[] = ['title' => $heading, 'content' => $translatedBody];
            } elseif (str_starts_with($section['heading'], 'Checklist')) {
                if ($translatedBody !== null) {
                    $items = self::checklistItems($translatedBody, $label, $errors);
                    $spanishItems = self::checklistItems($section['body'], $label, $errors);
                    if (count($items) !== count($spanishItems)) {
                        $errors[] = "{$label}: ".count($items).' checklist items, the Spanish has '.count($spanishItems).'.';
                    }
                    $checklist = $items;
                }
            } elseif ($lessons === []) {
                $overview[] = $translatedBody === null ? null : "## {$heading}\n\n{$translatedBody}";
            } else {
                $closing[] = $translatedBody === null ? null : "## {$heading}\n\n{$translatedBody}";
            }
        }

        self::assertSame($name, $errors);

        return [
            'title' => $copy['title'],
            'description' => $description,
            'overview' => $overview,
            'lessons' => $lessons,
            'checklist' => $checklist,
            'closing' => $closing,
        ];
    }

    /**
     * What a translation lost of its original, as messages (empty: nothing).
     *
     * @return list<string>
     */
    public static function differences(string $spanish, string $translated, string $label): array
    {
        $errors = [];

        preg_match_all('/\d+(?:[.,\/:]\d+)*/', $spanish, $numbers);
        preg_match_all('/\d+(?:[.,\/:]\d+)*/', $translated, $kept);
        foreach (array_diff(array_unique($numbers[0]), $kept[0]) as $number) {
            $errors[] = "{$label}: the figure {$number} is missing";
        }

        preg_match_all('/\b[A-Z][A-Za-z]*[A-Z]\b/', $spanish, $acronyms);
        foreach (array_unique($acronyms[0]) as $acronym) {
            if (strlen($acronym) >= 3 && ! in_array($acronym, self::SPANISH_CAPS, true) && ! str_contains($translated, $acronym)) {
                $errors[] = "{$label}: {$acronym} is missing";
            }
        }

        $rows = fn (string $text) => array_values(array_filter(explode("\n", $text), fn (string $line) => str_starts_with(ltrim($line), '|')));
        [$spanishRows, $translatedRows] = [$rows($spanish), $rows($translated)];
        if (count($spanishRows) !== count($translatedRows)) {
            $errors[] = "{$label}: ".count($translatedRows).' table rows, the Spanish has '.count($spanishRows);
        } else {
            foreach ($spanishRows as $r => $row) {
                if (substr_count($row, '|') !== substr_count($translatedRows[$r], '|')) {
                    $errors[] = "{$label}: table row ".($r + 1).' does not have the Spanish row\'s cells';
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $spanish
     * @param  array<string, mixed>  $translated
     * @return list<string>
     */
    private static function quizDifferences(array $spanish, array $translated): array
    {
        $errors = [];
        $questions = $translated['preguntas'] ?? [];

        if (count($questions) !== count($spanish['preguntas'])) {
            return [count($questions).' questions, the Spanish has '.count($spanish['preguntas'])];
        }

        foreach ($spanish['preguntas'] as $q => $question) {
            $copy = $questions[$q];
            $label = "question {$question['numero']}";
            if (($copy['numero'] ?? null) !== $question['numero']) {
                $errors[] = "{$label}: out of order";
            }
            $errors = [...$errors, ...self::differences($question['pregunta'], $copy['pregunta'] ?? '', $label)];

            $options = $copy['opciones'] ?? [];
            if (count($options) !== count($question['opciones'])) {
                $errors[] = "{$label}: ".count($options).' options, the Spanish has '.count($question['opciones']);

                continue;
            }
            foreach ($question['opciones'] as $o => $option) {
                if (($options[$o]['letra'] ?? null) !== $option['letra'] || ($options[$o]['correcta'] ?? null) !== $option['correcta']) {
                    $errors[] = "{$label}, option {$option['letra']}: the letter or the right answer changed";
                }
                $errors = [...$errors, ...self::differences($option['texto'], $options[$o]['texto'] ?? '', "{$label}, option {$option['letra']}")];
            }
        }

        return $errors;
    }

    /**
     * @return array{title: string, preamble: string, sections: list<array{heading: string, body: string}>}
     */
    private static function split(string $markdown): array
    {
        $lines = explode("\n", rtrim(str_replace("\r\n", "\n", $markdown), "\n"));

        if (! str_starts_with($lines[0] ?? '', '# ')) {
            throw new RuntimeException('A module file must start with its "# " title.');
        }

        $preamble = [];
        $sections = [];
        foreach (array_slice($lines, 1) as $line) {
            if (str_starts_with($line, '## ')) {
                $sections[] = ['heading' => substr($line, 3), 'body' => []];
            } elseif ($sections === []) {
                $preamble[] = $line;
            } else {
                $sections[count($sections) - 1]['body'][] = $line;
            }
        }

        return [
            'title' => trim(substr($lines[0], 2)),
            'preamble' => trim(implode("\n", $preamble), "\n"),
            'sections' => array_map(
                fn (array $section) => ['heading' => trim($section['heading']), 'body' => trim(implode("\n", $section['body']), "\n")],
                $sections,
            ),
        ];
    }

    /**
     * @param  list<string>  $errors
     * @return list<string>
     */
    private static function checklistItems(string $body, string $label, array &$errors): array
    {
        $items = [];
        foreach (explode("\n", $body) as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (preg_match('/^- \[ \] (.+)$/u', $line, $item) !== 1) {
                $errors[] = "{$label}: not a checklist item: {$line}";

                continue;
            }
            $items[] = $item[1];
        }

        return $items;
    }

    private static function untranslated(string $text): bool
    {
        return trim($text) === self::UNTRANSLATED;
    }

    /**
     * @param  list<string>  $errors
     */
    private static function assertSame(string $name, array $errors): void
    {
        if ($errors !== []) {
            throw new RuntimeException("{$name}:\n  ".implode("\n  ", array_unique($errors)));
        }
    }
}
