<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;

/**
 * A published two-module course with two lessons in each — small enough that
 * every percentage asserted below is exact rather than rounded.
 */
function twoModuleCourse(): Course
{
    $course = Course::factory()->create(['status' => 'published', 'price_eur' => 90000]);

    Module::factory()->count(2)
        ->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['course_id' => $course->id, 'price_eur' => 50000])
        ->each(fn (Module $module) => Lesson::factory()->count(2)
            ->sequence(fn ($seq) => ['order' => $seq->index + 1])
            ->create(['module_id' => $module->id]));

    return $course->fresh();
}

it('answers full_access the same way for an à-la-carte completer', function () {
    $course = twoModuleCourse();
    $modules = $course->modules;

    // Both modules bought separately: no enrolment row has module_id NULL.
    $student = User::factory()->create();
    foreach ($modules as $module) {
        Enrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'module_id' => $module->id,
        ]);
    }

    // Findings C13/C16, closed: the player, the dashboard and the catalog
    // share User::hasFullAccessTo() — every module watchable, however it was
    // bought. Before, the dashboard and the catalog looked only for a
    // module_id NULL row and showed "2 modules sur 2" next to a course that
    // read as partial, and checkout offered the full course again.
    $learn = $this->actingAs($student)->getJson("/api/courses/{$course->slug}/learn")->assertOk();

    expect($learn->json('data.course.full_access'))->toBeTrue()
        ->and($learn->json('data.course.owned_modules_count'))->toBe(2)
        ->and($learn->json('data.course.total_modules_count'))->toBe(2);

    $this->actingAs($student)->getJson('/api/enrollments')->assertOk()
        ->assertJsonPath('data.0.full_access', true)
        ->assertJsonPath('data.0.owned_modules_count', 2)
        ->assertJsonPath('data.0.total_modules_count', 2);

    $this->actingAs($student)->getJson("/api/courses/{$course->slug}")->assertOk()
        ->assertJsonPath('data.full_access', true)
        ->assertJsonPath('data.is_enrolled', true);
});

it('agrees on full_access when the whole course was bought in one go', function () {
    $course = twoModuleCourse();

    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => null,
    ]);

    // Bought in one go: the NULL row alone is full access.
    $this->actingAs($student)->getJson("/api/courses/{$course->slug}/learn")->assertOk()
        ->assertJsonPath('data.course.full_access', true);
    $this->actingAs($student)->getJson('/api/enrollments')->assertOk()
        ->assertJsonPath('data.0.full_access', true);

    $response = $this->actingAs($student)->getJson("/api/courses/{$course->slug}")->assertOk();

    expect($response->json('data.full_access'))->toBeTrue()
        ->and(collect($response->json('data.modules'))->pluck('is_owned')->all())->toBe([true, true]);
});

it('measures player progress against the lessons the student actually owns', function () {
    $course = twoModuleCourse();
    [$owned, $locked] = $course->modules;
    [$first, $second] = $owned->lessons;

    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => $owned->id,
    ]);

    LessonProgress::factory()->completed()->create([
        'user_id' => $student->id,
        'lesson_id' => $first->id,
    ]);

    $learn = $this->actingAs($student)->getJson("/api/courses/{$course->slug}/learn")->assertOk();

    // One of the buyer's TWO lessons: 50%. Counting the locked module's
    // lessons too would drop a student who has finished half of what they
    // paid for to a demoralising 25%, and point "reprendre" at a module they
    // cannot open.
    expect($learn->json('data.course.progress_percent'))->toBe(50)
        ->and($learn->json('data.next_lesson_id'))->toBe($second->id);

    LessonProgress::factory()->completed()->create([
        'user_id' => $student->id,
        'lesson_id' => $second->id,
    ]);

    $done = $this->actingAs($student)->getJson("/api/courses/{$course->slug}/learn")->assertOk();

    // Finishing everything owned reads as 100%, and the resume pointer falls
    // back inside the owned module rather than wandering into the locked one.
    expect($done->json('data.course.progress_percent'))->toBe(100)
        ->and($done->json('data.next_lesson_id'))->toBe($first->id)
        ->and($done->json('data.next_lesson_id'))->not->toBeIn($locked->lessons->pluck('id')->all());
});

it('tells the landing page which modules the viewer already owns', function () {
    $course = twoModuleCourse();
    [$owned, $unowned] = $course->modules;

    // A guest is offered the whole catalogue: nothing owned, nothing enrolled.
    $guest = $this->getJson("/api/courses/{$course->slug}")->assertOk();

    expect(collect($guest->json('data.modules'))->pluck('is_owned')->all())->toBe([false, false])
        ->and($guest->json('data.full_access'))->toBeFalse()
        ->and($guest->json('data.is_enrolled'))->toBeFalse();

    $student = User::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => $owned->id,
    ]);

    // is_owned is what the sales page reads to decide which modules go in the
    // basket: losing it sells a student a module they have already paid for.
    $partial = $this->actingAs($student)->getJson("/api/courses/{$course->slug}")->assertOk();
    $modules = collect($partial->json('data.modules'));

    expect($modules->firstWhere('id', $owned->id)['is_owned'])->toBeTrue()
        ->and($modules->firstWhere('id', $unowned->id)['is_owned'])->toBeFalse()
        ->and($partial->json('data.is_enrolled'))->toBeTrue()
        ->and($partial->json('data.full_access'))->toBeFalse();
});
