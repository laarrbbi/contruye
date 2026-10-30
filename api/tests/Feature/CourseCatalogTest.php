<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;

it('lists only published courses', function () {
    Course::factory()->create(['slug' => 'published-course']);
    Course::factory()->draft()->create(['slug' => 'draft-course']);

    $this->getJson('/api/courses')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'published-course');
});

it('returns 404 for a draft course by slug', function () {
    Course::factory()->draft()->create(['slug' => 'secret-draft']);

    $this->getJson('/api/courses/secret-draft')->assertNotFound();
});

it('returns the english title when Accept-Language is en', function () {
    Course::factory()->create([
        'slug' => 'bilingual-course',
        'title_es' => 'Título en español',
        'title_en' => 'English title',
    ]);

    $this->getJson('/api/courses', ['Accept-Language' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.0.title', 'English title');

    $this->getJson('/api/courses/bilingual-course', ['Accept-Language' => 'es'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Título en español');
});

it('computes lessons_count and total_duration_seconds across modules', function () {
    $course = Course::factory()->create();
    $moduleA = Module::factory()->for($course)->create(['order' => 1]);
    $moduleB = Module::factory()->for($course)->create(['order' => 2]);

    Lesson::factory()->for($moduleA)->create(['order' => 1, 'duration_seconds' => 600]);
    Lesson::factory()->for($moduleA)->create(['order' => 2, 'duration_seconds' => 900]);
    Lesson::factory()->for($moduleB)->create(['order' => 1, 'duration_seconds' => 300]);

    $this->getJson('/api/courses')
        ->assertOk()
        ->assertJsonPath('data.0.lessons_count', 3)
        ->assertJsonPath('data.0.total_duration_seconds', 1800);
});

it('shows a published course with ordered modules, lessons and is_enrolled false for guests', function () {
    $course = Course::factory()->create(['slug' => 'detail-course']);
    $module = Module::factory()->for($course)->create(['order' => 1, 'title_es' => 'Module un']);
    Lesson::factory()->for($module)->freePreview()->create(['order' => 1, 'title_es' => 'Leçon une']);

    $this->getJson('/api/courses/detail-course')
        ->assertOk()
        ->assertJsonPath('data.is_enrolled', false)
        ->assertJsonPath('data.modules.0.title', 'Module un')
        ->assertJsonPath('data.modules.0.lessons.0.title', 'Leçon une')
        ->assertJsonPath('data.modules.0.lessons.0.is_free_preview', true);
});

it('marks is_enrolled true for an enrolled user', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create(['slug' => 'enrolled-course']);
    Enrollment::factory()->for($user)->for($course)->create();

    $this->actingAs($user)
        ->getJson('/api/courses/enrolled-course')
        ->assertOk()
        ->assertJsonPath('data.is_enrolled', true);
});

it('keeps is_enrolled false for a user whose enrollment expired', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create(['slug' => 'expired-course']);
    Enrollment::factory()->for($user)->for($course)->create(['expires_at' => now()->subDay()]);

    $this->actingAs($user)
        ->getJson('/api/courses/expired-course')
        ->assertOk()
        ->assertJsonPath('data.is_enrolled', false);
});
