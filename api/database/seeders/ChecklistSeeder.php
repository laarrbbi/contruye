<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use App\Models\Course;
use Illuminate\Database\Seeder;

/**
 * Installs the curated per-module action checklists (data/checklists_v1.php),
 * keyed by module order. Idempotent: a module that already has items is left
 * untouched, so student check states survive re-seeding.
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

        $lists = require database_path('seeders/data/checklists_v1.php');
        $created = 0;

        foreach ($course->modules()->orderBy('order')->get() as $module) {
            $items = $lists[$module->order] ?? [];

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
