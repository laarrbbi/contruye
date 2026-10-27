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
        $course = Course::query()->where('slug', 'promotion-immobiliere-algerie')->firstOrFail();

        $karim = User::query()->firstOrCreate(
            ['email' => 'karim@example.dz'],
            [
                'name' => 'Karim Benali',
                'phone' => '0550123456',
                'password' => 'password',
                'role' => 'student',
                'locale' => 'fr',
                'email_verified_at' => now(),
            ],
        );

        $amel = User::query()->firstOrCreate(
            ['email' => 'amel@example.dz'],
            [
                'name' => 'Amel Cherif',
                'phone' => '0661234567',
                'password' => 'password',
                'role' => 'student',
                'locale' => 'ar',
                'email_verified_at' => now(),
            ],
        );

        // Karim: paid Chargily order -> enrollment via markPaidAndEnroll()
        // (the only sanctioned path from payment to enrollment).
        $karimOrder = Order::query()->firstOrCreate(
            [
                'user_id' => $karim->id,
                'course_id' => $course->id,
                'method' => 'chargily',
            ],
            [
                'amount' => 90000,
                'currency' => 'DZD',
                'scope' => 'full',
                'status' => 'pending',
                'chargily_checkout_id' => '01'.Str::lower(Str::random(24)),
            ],
        );

        if ($karimOrder->status !== 'paid') {
            $karimOrder->markPaidAndEnroll();
        }

        // Karim's progress in module 1: 3 lessons done, the 4th in progress.
        $module1Lessons = $course->modules()->orderBy('order')->firstOrFail()
            ->lessons()->orderBy('order')->take(4)->get();

        foreach ($module1Lessons as $index => $lesson) {
            LessonProgress::query()->firstOrCreate(
                ['user_id' => $karim->id, 'lesson_id' => $lesson->id],
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

        // Amel: à-la-carte order for 3 modules (10% volume tier:
        // 3 × 12 000 = 36 000 → 32 400), manual CCP awaiting review — NOT enrolled.
        $amelOrder = Order::query()->firstOrCreate(
            [
                'user_id' => $amel->id,
                'course_id' => $course->id,
                'method' => 'manual_ccp',
            ],
            [
                'amount' => 32400,
                'discount_percent' => 10,
                'currency' => 'DZD',
                'scope' => 'modules',
                'status' => 'awaiting_review',
                'receipt_path' => 'receipts/demo/amel-ccp.jpg',
            ],
        );

        if ($amelOrder->orderModules()->doesntExist()) {
            $firstThreeModules = $course->modules()->orderBy('order')->take(3)->get();
            foreach ($firstThreeModules as $module) {
                $amelOrder->orderModules()->create([
                    'module_id' => $module->id,
                    'unit_price' => $module->price_dzd ?? 12000,
                ]);
            }
        }
    }
}
