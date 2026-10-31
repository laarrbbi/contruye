<?php

namespace Database\Seeders;

use App\Models\ContentBlock;
use App\Models\Course;
use App\Models\CourseDocument;
use App\Models\Module;
use App\Models\Quiz;
use App\Services\PricingService;
use App\Support\ContentValidation;
use App\Support\Course\CourseSource;
use Illuminate\Database\Seeder;

/**
 * Installs the course from the instructor's package (curso/, version 4.0):
 * the 12 modules and Financials with their lessons, the course documents
 * (introduction, resources, annexes), the module quizzes and the final exam.
 *
 * Rules honoured here:
 * - Every text is stored as delivered, Markdown included (CourseSource says
 *   how a file is cut; CourseContentTest proves nothing is lost).
 * - English fields stay empty: the EN translation is a separate human pass,
 *   and the localized* accessors fall back to Spanish meanwhile.
 * - Passages containing "pendiente de validar…" would still be flagged for
 *   the admin's A VALIDAR badge; the delivered text has none.
 * - The checklists are ChecklistSeeder's; the province directory is
 *   ProvinceGuideSeeder's.
 */
class CurriculumV2Seeder extends Seeder
{
    public function run(): void
    {
        $source = CourseSource::fromConfig();
        $modules = $source->modules();
        $intro = $source->read('1_contenido/modulos/00_introduccion.md');

        // "## Promoción de viviendas de renta libre en España" and the
        // course's own one-paragraph answer to "Qué es este curso".
        preg_match('/^## (.+)$/m', $intro, $title);
        preg_match('/^> \*\*Qué es este curso\*\*\n> (.+)$/mu', $intro, $what);

        $course = Course::query()->firstOrCreate(
            ['slug' => 'promocion-inmobiliaria-espana'],
            [
                'title_es' => $title[1],
                // No English yet: a human translation is a separate pass.
                'title_en' => null,
                // Whole euros, IVA included. Placeholders until the owner
                // sets the launch prices.
                'price_eur' => 490,
                'module_discount_tiers' => PricingService::DEFAULT_TIERS,
                'status' => 'published',
            ],
        );

        $course->update([
            'title_es' => $title[1],
            'description_es' => $what[1],
            'status' => 'published',
        ]);

        $this->seedDocuments($course, $source);

        // Enrollments and orders reference modules, so the structure is only
        // (re)built when it is not already the one in the package.
        if ($course->modules()->count() === count($modules)) {
            $this->command?->info('Curriculum already installed — skipping the modules.');
            $this->seedQuizzes($course, $source);

            return;
        }

        $course->modules()->delete();

        foreach ($modules as $index => $data) {
            $module = $course->modules()->create([
                'order' => $index + 1,
                'kind' => $data['kind'],
                'title_es' => $data['title'],
                'title_en' => null,
                'description_es' => $data['description'],
                'description_en' => null,
                'price_eur' => 49,
            ]);

            $order = 0;
            foreach ($data['overview'] as $body) {
                $this->block($module, 'overview', ++$order, $body);
            }
            foreach ($data['closing'] as $body) {
                $this->block($module, 'section', ++$order, $body);
            }

            foreach ($data['lessons'] as $lessonIndex => $lesson) {
                $module->lessons()->create([
                    'order' => $lessonIndex + 1,
                    'title_es' => $lesson['title'],
                    'title_en' => null,
                    'content_es' => $lesson['content'],
                    'content_en' => null,
                    // The first two lessons of module 1 are open to everyone,
                    // as the course's plan has it.
                    'is_free_preview' => $index === 0 && $lessonIndex < 2,
                    'is_reflex_sheet' => false,
                    'duration_seconds' => 0,
                ]);
            }
        }

        $this->seedQuizzes($course, $source);

        $this->command?->info('Curriculum installed: '.count($modules).' modules, '
            .array_sum(array_map(fn (array $m) => count($m['lessons']), $modules)).' lessons.');
    }

    private function block(Module $module, string $type, int $order, string $body): void
    {
        ContentBlock::create([
            'module_id' => $module->id,
            'type' => $type,
            'order' => $order,
            'body_es' => $body,
            'body_en' => null,
            'needs_validation' => ContentValidation::flags($body),
        ]);
    }

    /**
     * Course documents are texts, not structure: refreshed on every run.
     */
    private function seedDocuments(Course $course, CourseSource $source): void
    {
        $order = 0;
        foreach ($source->documents() as $slug => $document) {
            CourseDocument::query()->updateOrCreate(
                ['course_id' => $course->id, 'slug' => $slug],
                [
                    'order' => ++$order,
                    'title_es' => $document['title'],
                    'body_es' => $document['body'],
                    'access' => $document['access'],
                ],
            );
        }
    }

    /**
     * One quiz per module (matched on the module file it belongs to) and the
     * final exam. Refreshed in place, so attempts keep their quiz.
     */
    private function seedQuizzes(Course $course, CourseSource $source): void
    {
        $quizzes = $source->quizzes();
        $modules = $course->modules()->orderBy('order')->get()->values();

        foreach ($source->modules() as $index => $data) {
            $quiz = $quizzes[$data['section']] ?? null;
            $module = $modules[$index] ?? null;

            if ($quiz === null || $module === null) {
                continue;
            }

            Quiz::query()->updateOrCreate(
                ['module_id' => $module->id],
                [
                    'course_id' => $course->id,
                    'kind' => 'module',
                    'title_es' => $quiz['titulo'],
                    'pass_mark' => CourseSource::passMark($quiz['minimo']),
                    'duration_minutes' => null,
                    'questions' => $quiz['preguntas'],
                ],
            );
        }

        $exam = $source->finalExam();
        Quiz::query()->updateOrCreate(
            ['course_id' => $course->id, 'kind' => 'final'],
            [
                'module_id' => null,
                'title_es' => $exam['titulo'],
                'pass_mark' => CourseSource::passMark($exam['minimo']),
                'duration_minutes' => (int) $exam['duracion_minutos'],
                'questions' => $exam['preguntas'],
            ],
        );
    }
}
