<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use App\Models\Course;
use App\Support\Course\CourseSource;
use Illuminate\Database\Seeder;

/**
 * Installs the checklist that closes each module ("## Checklist del Módulo N"
 * in the instructor's package), item by item and word for word.
 *
 * Run again after a new package, it brings each list up to the package while
 * keeping what students ticked: an item whose words are unchanged keeps its
 * row (and its ticks) wherever it moves; an item reworded in the same place,
 * between the same unchanged neighbours, is the same step and is updated in
 * place; an item the package adds is created and one it drops is deleted.
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
        $created = $updated = 0;

        foreach ($course->modules()->orderBy('order')->get()->values() as $index => $module) {
            $items = $lists[$index] ?? [];
            $existing = $module->checklistItems()->orderBy('order')->get()->values()->all();

            foreach ($this->align($existing, $items) as $order => [$item, $text]) {
                if ($item === null) {
                    ChecklistItem::create([
                        'module_id' => $module->id,
                        'order' => $order + 1,
                        'text_es' => $text,
                        'text_en' => null, // the translations are CourseTranslationSeeder's
                    ]);
                    $created++;

                    continue;
                }

                $item->fill(['order' => $order + 1, 'text_es' => $text]);
                if ($item->isDirty()) {
                    $item->save();
                    $updated++;
                }
            }
        }

        $this->command?->info("Checklists seeded: {$created} items created, {$updated} brought up to the package.");
    }

    /**
     * The package's list against the installed one, in the package's order:
     * [installed item or null, text] for each package item. Installed items
     * left without a partner are deleted here (their ticks go with them).
     *
     * @param  list<ChecklistItem>  $existing
     * @param  list<string>  $texts
     * @return list<array{0: ChecklistItem|null, 1: string}>
     */
    private function align(array $existing, array $texts): array
    {
        $result = [];
        $next = 0;          // first installed item not yet used
        $pending = [];      // package items since the last unchanged one

        foreach ($texts as $text) {
            $match = null;
            for ($i = $next; $i < count($existing); $i++) {
                if ($existing[$i]->text_es === $text) {
                    $match = $i;
                    break;
                }
            }

            if ($match === null) {
                $pending[] = $text;

                continue;
            }

            $result = [...$result, ...$this->pair(array_slice($existing, $next, $match - $next), $pending)];
            $result[] = [$existing[$match], $text];
            $pending = [];
            $next = $match + 1;
        }

        return [...$result, ...$this->pair(array_slice($existing, $next), $pending)];
    }

    /**
     * Between two unchanged items: reworded ones pair up in order, the rest
     * are new (null) or gone (deleted).
     *
     * @param  list<ChecklistItem>  $installed
     * @param  list<string>  $texts
     * @return list<array{0: ChecklistItem|null, 1: string}>
     */
    private function pair(array $installed, array $texts): array
    {
        foreach (array_slice($installed, count($texts)) as $gone) {
            $gone->delete();
        }

        return array_map(fn (string $text, int $i) => [$installed[$i] ?? null, $text], $texts, array_keys($texts));
    }
}
