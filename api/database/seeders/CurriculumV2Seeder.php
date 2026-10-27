<?php

namespace Database\Seeders;

use App\Models\ContentBlock;
use App\Models\Course;
use App\Models\Lesson;
use App\Services\PricingService;
use App\Support\ContentValidation;
use Illuminate\Database\Seeder;

/**
 * Installs the v2.0 curriculum (13 modules), replacing the v1 program.
 *
 * Rules honoured here:
 * - Legal figures are copied verbatim from the source and never reworded.
 * - Arabic fields stay empty: the AR translation is a separate human pass,
 *   and the localized* accessors fall back to French meanwhile.
 * - Passages containing "à vérifier / à confirmer / à verrouiller / à valider"
 *   are flagged `needs_validation` so admins see an "A VALIDER" badge and
 *   they land in the generated content-review report. They are never removed.
 * - partner_slot blocks carry only the KIND of provider needed. No contact is
 *   ever invented; real contacts come from the `partners` table.
 */
class CurriculumV2Seeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('seeders/data/curriculum_v2.php');

        $course = Course::query()->firstOrCreate(
            ['slug' => 'promotion-immobiliere-algerie'],
            [
                'title_fr' => $data['course']['title_fr'],
                // The course TITLE is unchanged from v1, so its existing human
                // translation still applies. The new v2 description is NOT
                // translated here — that is a separate human pass.
                'title_ar' => $data['course']['title_ar'],
                'price_dzd' => 90000,
                'price_eur' => 300,
                'module_discount_tiers' => PricingService::DEFAULT_TIERS,
                'status' => 'published',
            ],
        );

        $course->update([
            'title_fr' => $data['course']['title_fr'],
            'description_fr' => $data['course']['description_fr'],
            'status' => 'published',
        ]);

        // The v2.0 curriculum replaces v1 wholesale. Enrollments reference
        // modules, so only re-seed when the structure is not already v2.
        if ($course->modules()->count() === count($data['modules'])) {
            $this->command?->info('Curriculum v2.0 already installed — skipping.');

            return;
        }

        $course->modules()->delete();

        foreach ($data['modules'] as $order => $moduleData) {
            $module = $course->modules()->create([
                'order' => $order,
                'title_fr' => $moduleData['title'],
                'title_ar' => null, // AR translation pending (human pass)
                'description_fr' => $moduleData['description'] ?? null,
                'description_ar' => null,
                'price_dzd' => 12000,
                'price_eur' => 40,
            ]);

            $this->seedBlocks($moduleData['blocks'] ?? [], moduleId: $module->id);

            foreach ($moduleData['lessons'] as $lessonIndex => $lessonData) {
                $lesson = $module->lessons()->create([
                    'order' => $lessonIndex + 1,
                    'title_fr' => $lessonData['title'],
                    'title_ar' => null,
                    'content_fr' => $lessonData['content'] ?? null,
                    'content_ar' => null,
                    'key_points_fr' => $lessonData['key_points'] ?? null,
                    'key_points_ar' => null,
                    // First lesson of the course stays a free preview so the
                    // landing page can still offer a taste.
                    'is_free_preview' => $order === 1 && $lessonIndex === 0,
                    'is_reflex_sheet' => $lessonData['is_reflex_sheet'] ?? false,
                    'duration_seconds' => 0,
                ]);

                $this->seedBlocks($lessonData['blocks'] ?? [], lessonId: $lesson->id);
            }
        }

        $this->command?->info('Curriculum v2.0 installed: '.count($data['modules']).' modules.');
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
                'body_fr' => $block['body'],
                'body_ar' => null,
                'partner_category' => $block['partner_category'] ?? null,
                'needs_validation' => ContentValidation::flags($block['body']),
            ]);
        }
    }
}
