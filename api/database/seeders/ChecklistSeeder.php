<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use App\Models\Course;
use App\Support\Course\CourseSource;
use Illuminate\Database\Seeder;

/**
 * Installs the checklist that closes each module ("## Checklist del Módulo N"
 * in the instructor's package), item by item and word for word. Idempotent: a
 * module that already has items is left untouched, so student check states
 * survive re-seeding.
 */
class ChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $course = Course::query()->where('slug', 'promocion-inmobiliaria-espana')->first();

        if ($course === null) {
            $this->command?->error('Seed the curriculum first (CurriculumV2Seeder).');

            return;
        }

        $lists = array_column(CourseSource::fromConfig()->modules(), 'checklist');
        $created = 0;

        foreach ($course->modules()->orderBy('order')->get()->values() as $index => $module) {
            $items = $lists[$index] ?? [];

            if ($items === [] || $module->checklistItems()->exists()) {
                continue;
            }

            foreach ($items as $index => $text) {
                ChecklistItem::create([
                    'module_id' => $module->id,
                    'order' => $index + 1,
                    'text_es' => $text,
                    'text_en' => null, // human EN pass pending, same as the curriculum
                ]);
                $created++;
            }
        }

        $this->command?->info("Checklists seeded: {$created} items.");
    }
}
