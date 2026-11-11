<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Course;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The launch price the owner decided: 20 % off the course (€499 → €399) until
 * 30 November 2026 at 23:59, Madrid time. A real deadline, the same for every
 * visitor, that never moves (constitution I: no countdown that resets); after
 * it the course is back at its price. Idempotent: run again, nothing changes.
 *
 * The default seed runs it (and so the Vercel preview). On the live server,
 * `php artisan db:seed --class=LaunchCampaignSeeder`, or the same campaign in
 * admin → Campañas.
 */
class LaunchCampaignSeeder extends Seeder
{
    public const ENDS_AT = '2026-11-30 23:59:59';

    public function run(): void
    {
        $course = Course::query()->where('slug', 'promocion-inmobiliaria-espana')->first();
        if ($course === null) {
            $this->command?->error('Seed the curriculum first (CurriculumV2Seeder).');

            return;
        }

        Campaign::query()->firstOrCreate(
            ['course_id' => $course->id, 'label_es' => 'Precio de lanzamiento'],
            [
                'label_en' => 'Launch price',
                'label_fr' => 'Prix de lancement',
                'label_ar' => 'سعر الإطلاق',
                'label_ru' => 'Цена запуска',
                'label_uk' => 'Ціна запуску',
                'percent_off' => 20,
                'starts_at' => Carbon::parse('2026-11-11 00:00:00', config('app.business_timezone'))->utc(),
                'ends_at' => Carbon::parse(self::ENDS_AT, config('app.business_timezone'))->utc(),
                'is_active' => true,
            ],
        );
    }
}
