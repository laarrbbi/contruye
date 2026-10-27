<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@example.es'],
            [
                'name' => 'Admin Construye',
                'phone' => '600000000',
                'password' => 'password',
                'role' => 'admin',
                'locale' => 'es',
                'email_verified_at' => now(),
            ],
        );
    }
}
