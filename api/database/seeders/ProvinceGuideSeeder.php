<?php

namespace Database\Seeders;

use App\Models\Province;
use App\Models\ProvinceGuide;
use App\Support\Course\CourseSource;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Anexo A of the course, one guide per province: what to ask and where, phase
 * by phase. Reference data, refreshed on every run.
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

            ProvinceGuide::query()->updateOrCreate(
                ['province_id' => $province->id],
                ['ccaa' => $guide['ccaa'], 'section_es' => $guide['section'], 'data' => $guide['data']],
            );
            $count++;
        }

        $this->command?->info("Province guides installed: {$count}.");
    }
}
