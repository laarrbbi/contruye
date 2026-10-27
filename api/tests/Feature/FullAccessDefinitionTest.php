<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\User;

/**
 * C13/C16: "full access" means the student can watch every module of the
 * course, whether they bought it at once (one enrollment with module_id NULL)
 * or module by module until they own them all. FullAccessContractTest pins
 * that the three endpoints agree; this pins what the definition decides.
 */
function alaCarteCompleter(): array
{
    $course = Course::factory()->create(['status' => 'published', 'price_dzd' => 90000]);
    $modules = Module::factory()->count(3)
        ->sequence(fn ($seq) => ['order' => $seq->index + 1])
        ->create(['course_id' => $course->id, 'price_dzd' => 12000]);
    $student = User::factory()->create();

    foreach ($modules as $module) {
        Enrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'module_id' => $module->id,
        ]);
    }

    return [$course, $student, $modules];
}

it('does not sell the full course again to someone who owns every module', function () {
    [$course, $student] = alaCarteCompleter();

    $this->actingAs($student)
        ->postJson('/api/checkout/quote', ['course_id' => $course->id, 'scope' => 'full', 'currency' => 'DZD'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['course_id']);

    $this->actingAs($student)
        ->postJson('/api/checkout', ['course_id' => $course->id, 'scope' => 'full', 'method' => 'manual_ccp'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['course_id']);

    $this->assertDatabaseCount('orders', 0);
});

it('stops counting as full access when the course gains a module they do not own', function () {
    [$course, $student] = alaCarteCompleter();
    Module::factory()->create(['course_id' => $course->id, 'order' => 4, 'price_dzd' => 12000]);

    $this->actingAs($student);

    expect($this->getJson("/api/courses/{$course->slug}")->json('data.full_access'))->toBeFalse()
        ->and($this->getJson('/api/enrollments')->json('data.0.full_access'))->toBeFalse()
        ->and($this->getJson("/api/courses/{$course->slug}/learn")->json('data.course.full_access'))->toBeFalse();
});

it('does not count expired module enrollments', function () {
    [$course, $student, $modules] = alaCarteCompleter();
    Enrollment::query()->where('module_id', $modules[0]->id)->update(['expires_at' => now()->subDay()]);

    expect($student->hasFullAccessTo($course))->toBeFalse();
});
