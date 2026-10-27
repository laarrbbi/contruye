<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\LessonProgress;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $course = Course::query()->where('slug', 'promocion-inmobiliaria-espana')->firstOrFail();

        $javier = User::query()->firstOrCreate(
            ['email' => 'javier@example.es'],
            [
                'name' => 'Javier Morales',
                'phone' => '612345678',
                'password' => 'password',
                'role' => 'student',
                'locale' => 'es',
                'email_verified_at' => now(),
            ],
        );

        $lucia = User::query()->firstOrCreate(
            ['email' => 'lucia@example.es'],
            [
                'name' => 'Lucía Fernández',
                'phone' => '623456789',
                'password' => 'password',
                'role' => 'student',
                'locale' => 'en',
                'email_verified_at' => now(),
            ],
        );

        // Javier: paid card order -> enrollment via markPaidAndEnroll()
        // (the only sanctioned path from payment to enrollment).
        $javierOrder = Order::query()->firstOrCreate(
            [
                'user_id' => $javier->id,
                'course_id' => $course->id,
                'method' => 'stripe',
            ],
            [
                'amount' => 490,
                'currency' => 'EUR',
                'scope' => 'full',
                'status' => 'pending',
                'stripe_session_id' => 'cs_test_'.Str::random(24),
            ],
        );

        if ($javierOrder->status !== 'paid') {
            $javierOrder->markPaidAndEnroll();
        }

        // Javier's progress in phase 1: 3 lessons done, the 4th in progress.
        $module1Lessons = $course->modules()->orderBy('order')->firstOrFail()
            ->lessons()->orderBy('order')->take(4)->get();

        foreach ($module1Lessons as $index => $lesson) {
            LessonProgress::query()->firstOrCreate(
                ['user_id' => $javier->id, 'lesson_id' => $lesson->id],
                $index < 3
                    ? [
                        'completed_at' => now()->subDays(3 - $index),
                        'last_position_seconds' => $lesson->duration_seconds,
                    ]
                    : [
                        'completed_at' => null,
                        'last_position_seconds' => 450,
                    ],
            );
        }

        // Lucía: à-la-carte order for 3 phases (10% volume tier:
        // 3 × 49 = 147 → 132), bank transfer awaiting review — NOT enrolled.
        $luciaOrder = Order::query()->firstOrCreate(
            [
                'user_id' => $lucia->id,
                'course_id' => $course->id,
                'method' => 'manual_transfer',
            ],
            [
                'amount' => 132,
                'discount_percent' => 10,
                'currency' => 'EUR',
                'scope' => 'modules',
                'status' => 'awaiting_review',
                'receipt_path' => 'receipts/demo/lucia-transferencia.jpg',
            ],
        );

        if ($luciaOrder->orderModules()->doesntExist()) {
            $firstThreeModules = $course->modules()->orderBy('order')->take(3)->get();
            foreach ($firstThreeModules as $module) {
                $luciaOrder->orderModules()->create([
                    'module_id' => $module->id,
                    'unit_price' => $module->price_eur ?? 49,
                ]);
            }
        }
    }
}
