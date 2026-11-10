<?php

namespace Database\Seeders;

use App\Models\Province;
use App\Models\ProvinceGuide;
use App\Support\Course\CourseSource;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Anexo A of the course, one guide per province: what to ask and where, phase
 * by phase. Reference data, refreshed on every run; run it before
 * CourseTranslationSeeder, which translates these rows.
 *
 * The package names some provinces in one language ("Alicante", "Álava"); the
 * provinces table uses the official INE name ("Alicante/Alacant",
 * "Araba/Álava"), so either half of a name matches.
 */
class ProvinceGuideSeeder extends Seeder
{
    public function run(): void
    {
        $provinces = Province::query()->get();

        if ($provinces->isEmpty()) {
            $this->command?->error('Seed the provinces first (ProvinceSeeder).');

            return;
        }

        $count = 0;
        foreach (CourseSource::fromConfig()->provinceGuides() as $guide) {
            $province = $provinces->first(fn (Province $province) => in_array(
                $guide['name'],
                [$province->name_es, ...explode('/', $province->name_es)],
                true,
            ));

            if ($province === null) {
                throw new RuntimeException("No province matches {$guide['name']}");
            }

            $existing = ProvinceGuide::query()->where('province_id', $province->id)->first();
            $values = ['ccaa' => $guide['ccaa'], 'section_es' => $guide['section'], 'data' => $guide['data']];
            // New rows from a new package: the translated copies were made from
            // the old ones, so they go until CourseTranslationSeeder makes them
            // again (the Spanish is read meanwhile, never a stale translation).
            if ($existing !== null && $existing->data != $guide['data']) {
                foreach (config('locales.supported') as $locale) {
                    if ($locale !== config('locales.default')) {
                        $values["data_{$locale}"] = null;
                    }
                }
            }
            ProvinceGuide::query()->updateOrCreate(['province_id' => $province->id], $values);
            $count++;
        }

        $this->command?->info("Province guides installed: {$count}.");
    }
}
