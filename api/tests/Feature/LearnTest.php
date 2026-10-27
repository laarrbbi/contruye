<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;

it('returns 401 for guests', function () {
    Course::factory()->create(['slug' => 'guest-course']);

    $this->getJson('/api/courses/guest-course/learn')->assertUnauthorized();
});

it('returns 403 for an authenticated user who is not enrolled', function () {
    $user = User::factory()->create();
    Course::factory()->create(['slug' => 'locked-course']);

    $this->actingAs($user)
        ->getJson('/api/courses/locked-course/learn')
        ->assertForbidden();
});

it('lets an admin open the player without an enrollment', function () {
    $admin = User::factory()->admin()->create();
    Course::factory()->create(['slug' => 'admin-course']);

    $this->actingAs($admin)
        ->getJson('/api/courses/admin-course/learn')
        ->assertOk()
        ->assertJsonPath('data.course.slug', 'admin-course');
});

it('returns ordered modules and lessons with the user progress and next lesson', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create(['slug' => 'player-course']);
    Enrollment::factory()->for($user)->for($course)->create();

    // Created out of order on purpose: the API must sort by `order`.
    $moduleB = Module::factory()->for($course)->create(['order' => 2]);
    $moduleA = Module::factory()->for($course)->create(['order' => 1]);

    $lesson2 = Lesson::factory()->for($moduleA)->create(['order' => 2]);
    $lesson1 = Lesson::factory()->for($moduleA)->create(['order' => 1]);
    $lesson3 = Lesson::factory()->for($moduleB)->create(['order' => 1]);

    LessonProgress::factory()->completed()->for($user)->for($lesson1)
        ->create(['last_position_seconds' => 300]);
    LessonProgress::factory()->for($user)->for($lesson2)
        ->create(['last_position_seconds' => 45]);

    $this->actingAs($user)
        ->getJson('/api/courses/player-course/learn')
        ->assertOk()
        ->assertJsonPath('data.course.id', $course->id)
        ->assertJsonPath('data.course.slug', 'player-course')
        // 1 of 3 lessons completed -> round(33.33) = 33
        ->assertJsonPath('data.course.progress_percent', 33)
        ->assertJsonPath('data.modules.0.id', $moduleA->id)
        ->assertJsonPath('data.modules.1.id', $moduleB->id)
        ->assertJsonPath('data.modules.0.lessons.0.id', $lesson1->id)
        ->assertJsonPath('data.modules.0.lessons.1.id', $lesson2->id)
        ->assertJsonPath('data.modules.1.lessons.0.id', $lesson3->id)
        ->assertJsonPath('data.modules.0.lessons.0.is_completed', true)
        ->assertJsonPath('data.modules.0.lessons.0.last_position_seconds', 300)
        ->assertJsonPath('data.modules.0.lessons.1.is_completed', false)
        ->assertJsonPath('data.modules.0.lessons.1.last_position_seconds', 45)
        ->assertJsonPath('data.modules.1.lessons.0.is_completed', false)
        ->assertJsonPath('data.modules.1.lessons.0.last_position_seconds', 0)
        // First uncompleted lesson in module/lesson order.
        ->assertJsonPath('data.next_lesson_id', $lesson2->id);
});

it('falls back to the first lesson as next_lesson_id when everything is completed', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create(['slug' => 'finished-course']);
    Enrollment::factory()->for($user)->for($course)->create();

    $module = Module::factory()->for($course)->create(['order' => 1]);
    $lesson1 = Lesson::factory()->for($module)->create(['order' => 1]);
    $lesson2 = Lesson::factory()->for($module)->create(['order' => 2]);

    LessonProgress::factory()->completed()->for($user)->for($lesson1)->create();
    LessonProgress::factory()->completed()->for($user)->for($lesson2)->create();

    $this->actingAs($user)
        ->getJson('/api/courses/finished-course/learn')
        ->assertOk()
        ->assertJsonPath('data.course.progress_percent', 100)
        ->assertJsonPath('data.next_lesson_id', $lesson1->id);
});

it('returns 0 percent and a null next lesson for a course without lessons', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create(['slug' => 'empty-course']);
    Enrollment::factory()->for($user)->for($course)->create();

    $this->actingAs($user)
        ->getJson('/api/courses/empty-course/learn')
        ->assertOk()
        ->assertJsonPath('data.course.progress_percent', 0)
        ->assertJsonPath('data.next_lesson_id', null);
});

it('returns arabic titles when Accept-Language is ar', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create([
        'slug' => 'arabic-course',
        'title_es' => 'Titre français',
        'title_en' => 'عنوان الدورة',
    ]);
    Enrollment::factory()->for($user)->for($course)->create();

    $module = Module::factory()->for($course)->create([
        'order' => 1,
        'title_es' => 'Module un',
        'title_en' => 'الوحدة الأولى',
    ]);
    Lesson::factory()->for($module)->create([
        'order' => 1,
        'title_es' => 'Leçon une',
        'title_en' => 'الدرس الأول',
    ]);

    $this->actingAs($user)
        ->getJson('/api/courses/arabic-course/learn', ['Accept-Language' => 'ar'])
        ->assertOk()
        ->assertJsonPath('data.course.title', 'عنوان الدورة')
        ->assertJsonPath('data.modules.0.title', 'الوحدة الأولى')
        ->assertJsonPath('data.modules.0.lessons.0.title', 'الدرس الأول');
});

it('exposes attachments as name and index only, never storage paths', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create(['slug' => 'attachments-course']);
    Enrollment::factory()->for($user)->for($course)->create();

    $module = Module::factory()->for($course)->create(['order' => 1]);
    Lesson::factory()->for($module)->create([
        'order' => 1,
        'attachments' => [
            ['name' => 'Support.pdf', 'path' => 'lessons/attachments/secret-file.pdf'],
            ['name' => 'Exercices.pdf', 'path' => 'lessons/attachments/other-secret.pdf'],
        ],
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/courses/attachments-course/learn')
        ->assertOk()
        ->assertJsonPath('data.modules.0.lessons.0.attachments.0.name', 'Support.pdf')
        ->assertJsonPath('data.modules.0.lessons.0.attachments.0.index', 0)
        ->assertJsonPath('data.modules.0.lessons.0.attachments.1.name', 'Exercices.pdf')
        ->assertJsonPath('data.modules.0.lessons.0.attachments.1.index', 1)
        ->assertJsonMissingPath('data.modules.0.lessons.0.attachments.0.path');

    expect($response->getContent())->not->toContain('secret-file.pdf');
});
