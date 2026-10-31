<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

/**
 * Reference data — always seeded, in every environment. Idempotent: rows are
 * matched on their official code so re-running never duplicates them, and a
 * renamed province would be corrected in place.
 */
class ProvinceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (require database_path('seeders/data/provinces.php') as $province) {
            Province::query()->updateOrCreate(
                ['code' => $province['code']],
                array_intersect_key($province, array_flip(['name_es', 'name_en', 'name_fr', 'name_ar', 'name_ru', 'name_uk'])),
            );
        }
    }
}
