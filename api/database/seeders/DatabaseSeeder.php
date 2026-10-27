<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Demo accounts with the password "password", invented students with
        // paid orders (they would show in revenue and in the public student
        // count) and coupons whose codes are in the source. Never on the live
        // site: production installs with `php artisan app:install`.
        if (app()->isProduction()) {
            throw new RuntimeException(
                'DatabaseSeeder seeds demo accounts and orders and refuses to run in production. '
                .'Use `php artisan app:install`.'
            );
        }

        $this->call([
            WilayaSeeder::class,
            AdminSeeder::class,
            CurriculumV2Seeder::class,
            ChecklistSeeder::class,
            StudentSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
