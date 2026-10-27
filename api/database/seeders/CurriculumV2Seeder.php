<?php

namespace Database\Seeders;

use App\Models\ContentBlock;
use App\Models\Course;
use App\Models\Lesson;
use App\Services\PricingService;
use App\Support\ContentValidation;
use Illuminate\Database\Seeder;

/**
 * Installs the 12-phase curriculum.
 *
 * Rules honoured here:
 * - Legal figures are copied verbatim from the source and never reworded.
 * - English fields stay empty: the EN translation is a separate human pass,
 *   and the localized* accessors fall back to Spanish meanwhile.
 * - Passages containing "pendiente de validar / verificar / confirmar" (or
 *   "por validar…") are flagged `needs_validation` so admins see an
 *   "A VALIDAR" badge and they land in the generated content-review report.
 *   They are never removed.
 * - partner_slot blocks carry only the KIND of provider needed. No contact is
 *   ever invented; real contacts come from the `partners` table.
 */
class CurriculumV2Seeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('seeders/data/curriculum_v2.php');

        $course = Course::query()->firstOrCreate(
            ['slug' => 'promocion-inmobiliaria-espana'],
            [
                'title_es' => $data['course']['title_es'],
                // No English yet: a human translation is a separate pass.
                'title_en' => null,
                // Whole euros, IVA included. 12 phases à la carte cost
                // 12 × 49 = 588 €, so full access is the best deal (−17 %).
                'price_eur' => 490,
                'module_discount_tiers' => PricingService::DEFAULT_TIERS,
                'status' => 'published',
            ],
        );

        $course->update([
            'title_es' => $data['course']['title_es'],
            'description_es' => $data['course']['description_es'],
            'status' => 'published',
        ]);

        // Enrollments reference modules, so only (re)seed when the structure
        // is not already the one in the data file.
        if ($course->modules()->count() === count($data['modules'])) {
            $this->command?->info('Curriculum already installed — skipping.');

            return;
        }

        $course->modules()->delete();

        foreach ($data['modules'] as $order => $moduleData) {
            $module = $course->modules()->create([
                'order' => $order,
                'title_es' => $moduleData['title'],
                'title_en' => null, // EN translation pending (human pass)
                'description_es' => $moduleData['description'] ?? null,
                'description_en' => null,
                'price_eur' => 49,
            ]);

            $this->seedBlocks($moduleData['blocks'] ?? [], moduleId: $module->id);

            foreach ($moduleData['lessons'] as $lessonIndex => $lessonData) {
                $lesson = $module->lessons()->create([
                    'order' => $lessonIndex + 1,
                    'title_es' => $lessonData['title'],
                    'title_en' => null,
                    'content_es' => $lessonData['content'] ?? null,
                    'content_en' => null,
                    'key_points_es' => $lessonData['key_points'] ?? null,
                    'key_points_en' => null,
                    // First lesson of the course stays a free preview so the
                    // landing page can still offer a taste.
                    'is_free_preview' => $order === 1 && $lessonIndex === 0,
                    'is_reflex_sheet' => $lessonData['is_reflex_sheet'] ?? false,
                    'duration_seconds' => 0,
                ]);

                $this->seedBlocks($lessonData['blocks'] ?? [], lessonId: $lesson->id);
            }
        }

        $this->command?->info('Curriculum installed: '.count($data['modules']).' modules.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function seedBlocks(array $blocks, ?int $moduleId = null, ?int $lessonId = null): void
    {
        foreach ($blocks as $index => $block) {
            ContentBlock::create([
                'module_id' => $moduleId,
                'lesson_id' => $lessonId,
                'type' => $block['type'],
                'order' => $index + 1,
                'body_es' => $block['body'],
                'body_en' => null,
                'partner_category' => $block['partner_category'] ?? null,
                'needs_validation' => ContentValidation::flags($block['body']),
            ]);
        }
    }
}
