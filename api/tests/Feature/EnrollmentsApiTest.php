<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;

it('requires authentication', function () {
    $this->getJson('/api/enrollments')->assertUnauthorized();
});

it('computes progress_percent from completed lesson progress rows', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create();
    $module = Module::factory()->for($course)->create(['order' => 1]);
    $lessons = Lesson::factory()->for($module)->count(3)->create();

    Enrollment::factory()->for($user)->for($course)->create();

    // Two lessons completed by the user...
    LessonProgress::factory()->completed()->create([
        'user_id' => $user->id,
        'lesson_id' => $lessons[0]->id,
    ]);
    LessonProgress::factory()->completed()->create([
        'user_id' => $user->id,
        'lesson_id' => $lessons[1]->id,
    ]);
    // ...one merely started (must not count)...
    LessonProgress::factory()->create([
        'user_id' => $user->id,
        'lesson_id' => $lessons[2]->id,
        'completed_at' => null,
    ]);
    // ...and someone else's completion (must not count either).
    LessonProgress::factory()->completed()->create([
        'lesson_id' => $lessons[2]->id,
    ]);

    $this->actingAs($user)
        ->getJson('/api/enrollments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.course.id', $course->id)
        ->assertJsonPath('data.0.course.slug', $course->slug)
        ->assertJsonPath('data.0.course.progress_percent', 67); // round(2/3 * 100)
});

it('returns 0 progress for a course without lessons', function () {
    $user = User::factory()->create();
    $enrollment = Enrollment::factory()->for($user)->create();

    $this->actingAs($user)
        ->getJson('/api/enrollments')
        ->assertOk()
        ->assertJsonPath('data.0.id', $enrollment->id)
        ->assertJsonPath('data.0.course.progress_percent', 0)
        ->assertJsonPath('data.0.expires_at', null);
});

it('excludes expired enrollments', function () {
    $user = User::factory()->create();
    $active = Enrollment::factory()->for($user)->create(['expires_at' => now()->addYear()]);
    Enrollment::factory()->for($user)->create(['expires_at' => now()->subDay()]);

    $this->actingAs($user)
        ->getJson('/api/enrollments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id);
});

it('does not leak other users enrollments', function () {
    $user = User::factory()->create();
    Enrollment::factory()->create(); // someone else's

    $this->actingAs($user)
        ->getJson('/api/enrollments')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
