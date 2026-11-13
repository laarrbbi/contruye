<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use Database\Seeders\PreviewVideoSeeder;

/*
 * The preview's lesson videos (package v5): each on the lesson its heading
 * names, with its length; a lesson that is not there is left alone.
 */

it('puts each video on the lesson its heading names, with its length', function () {
    $course = Course::factory()->create();
    $lessons = [];
    foreach (PreviewVideoSeeder::VIDEOS as $order => $videos) {
        $module = Module::factory()->create(['course_id' => $course->id, 'kind' => 'phase', 'order' => $order]);
        foreach ($videos as $index => [$title]) {
            $lessons[$title] = Lesson::factory()->create(['module_id' => $module->id, 'order' => $index + 1, 'title_es' => $title, 'bunny_video_id' => null, 'duration_seconds' => 0]);
        }
    }
    // Module 3 has no video yet.
    $third = Module::factory()->create(['course_id' => $course->id, 'kind' => 'phase', 'order' => 3]);
    $untouched = Lesson::factory()->create(['module_id' => $third->id, 'order' => 1, 'title_es' => 'Lección 3.1 — Otra', 'bunny_video_id' => null]);

    (new PreviewVideoSeeder)->run();

    expect($lessons['Lección 1.1 — Define el producto y encuentra el solar']->fresh())
        ->bunny_video_id->toBe('construye-m1-l1-1-v5')
        ->duration_seconds->toBe(230);
    expect($lessons['Lección 2.5 — Negociación, estructura y aprobación de la inversión']->fresh())
        ->bunny_video_id->toBe('construye-m2-l2-5-v5')
        ->duration_seconds->toBe(176);
    expect(Lesson::query()->whereNotNull('bunny_video_id')->count())->toBe(9)
        ->and($untouched->fresh()->bunny_video_id)->toBeNull();

    // Every video it names is in the site's files, with its cover and subtitles.
    foreach (PreviewVideoSeeder::VIDEOS as $videos) {
        foreach ($videos as [, $id]) {
            foreach (['mp4', 'jpg', 'vtt'] as $extension) {
                expect(base_path("../web/public/video/{$id}.{$extension}"))->toBeFile();
            }
        }
    }
});
