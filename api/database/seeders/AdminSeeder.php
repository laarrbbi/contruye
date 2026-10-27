<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@example.dz'],
            [
                'name' => 'Admin Ebni',
                'phone' => '0550000000',
                'password' => 'password',
                'role' => 'admin',
                'locale' => 'fr',
                'email_verified_at' => now(),
            ],
        );
    }
}
