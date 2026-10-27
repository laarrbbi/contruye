<?php

namespace Database\Seeders;

use App\Models\Wilaya;
use Illuminate\Database\Seeder;

/**
 * Reference data — always seeded, in every environment. Idempotent: rows are
 * matched on their official code so re-running never duplicates them, and a
 * renamed wilaya would be corrected in place.
 */
class WilayaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (require database_path('seeders/data/wilayas.php') as $wilaya) {
            Wilaya::query()->updateOrCreate(
                ['code' => $wilaya['code']],
                ['name_fr' => $wilaya['name_fr'], 'name_ar' => $wilaya['name_ar']],
            );
        }
    }
}
