<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Quiz;
use App\Support\Course\CourseSource;
use App\Support\Course\CourseTranslation;
use Illuminate\Database\Seeder;

/**
 * Loads the translations in traducciones/<locale>/ into the `_<locale>`
 * columns of the course, its documents, modules, overview and closing
 * blocks, lessons, checklists and quizzes. CourseTranslation checks each
 * piece against the Spanish first; one mismatch and nothing is written.
 *
 * For a locale with a folder, the files are the source of truth and are
 * re-read on every run: a piece left `<!-- es -->` or a file not written yet
 * empties its column, and the student reads the Spanish. A locale with no
 * folder is not touched. The Spanish columns never are.
 */
class CourseTranslationSeeder extends Seeder
{
    public function run(): void
    {
        $course = Course::query()->where('slug', 'promocion-inmobiliaria-espana')->first();

        if ($course === null) {
            $this->command?->error('Seed the curriculum first (CurriculumV2Seeder).');

            return;
        }

        foreach (CourseTranslation::available() as $locale) {
            $translation = CourseTranslation::fromConfig($locale);

            // Read and check everything before writing anything.
            $meta = $translation->course();
            $documents = $translation->documents();
            $modules = $translation->modules();
            $assessments = $translation->assessments();

            $course->update([
                "title_{$locale}" => $meta['title'],
                "description_{$locale}" => $meta['description'],
            ]);

            foreach ($course->documents()->get() as $document) {
                $document->update([
                    "title_{$locale}" => $meta['documents'][$document->slug] ?? null,
                    "body_{$locale}" => $documents[$document->slug] ?? null,
                ]);
            }

            $pieces = 0;
            foreach ($course->modules()->orderBy('order')->get()->values() as $index => $module) {
                $data = $modules[$index] ?? null;

                $module->update([
                    "title_{$locale}" => $data['title'] ?? null,
                    "description_{$locale}" => $data['description'] ?? null,
                ]);

                // CurriculumV2Seeder stores the overview sections, then the
                // closing ones, in order.
                $blocks = $data === null ? [] : [...$data['overview'], ...$data['closing']];
                foreach ($module->contentBlocks()->whereNull('lesson_id')->orderBy('order')->get()->values() as $b => $block) {
                    $block->update(["body_{$locale}" => $blocks[$b] ?? null]);
                }

                foreach ($module->lessons()->orderBy('order')->get()->values() as $l => $lesson) {
                    $lesson->update([
                        "title_{$locale}" => $data['lessons'][$l]['title'] ?? null,
                        "content_{$locale}" => $data['lessons'][$l]['content'] ?? null,
                    ]);
                    $pieces += isset($data['lessons'][$l]['content']) ? 1 : 0;
                }

                foreach ($module->checklistItems()->orderBy('order')->get()->values() as $c => $item) {
                    $item->update(["text_{$locale}" => $data['checklist'][$c] ?? null]);
                }
            }

            $this->assessments($course, $locale, $assessments);

            $this->command?->info(sprintf(
                '%s: %d of %d modules, %d lessons translated in full, %d quizzes%s.',
                $locale, count($modules), $course->modules()->count(), $pieces,
                count($assessments['quizzes']), $assessments['exam'] ? ' and the exam' : '',
            ));
        }
    }

    /**
     * @param  array{quizzes: array<string, array<string, mixed>>, exam: ?array<string, mixed>}  $assessments
     */
    private function assessments(Course $course, string $locale, array $assessments): void
    {
        $sections = array_column(CourseSource::fromConfig()->modules(), 'section');
        $modules = $course->modules()->orderBy('order')->pluck('id')->values();

        foreach ($sections as $index => $section) {
            $quiz = Quiz::query()->where('module_id', $modules[$index] ?? 0)->first();
            $quiz?->update([
                "title_{$locale}" => $assessments['quizzes'][$section]['titulo'] ?? null,
                "questions_{$locale}" => $assessments['quizzes'][$section]['preguntas'] ?? null,
            ]);
        }

        Quiz::query()->where('course_id', $course->id)->where('kind', 'final')->first()?->update([
            "title_{$locale}" => $assessments['exam']['titulo'] ?? null,
            "questions_{$locale}" => $assessments['exam']['preguntas'] ?? null,
        ]);
    }
}
