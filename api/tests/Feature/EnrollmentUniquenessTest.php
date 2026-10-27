<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * C21/C40: one full-access enrollment per student per course, enforced by
 * the database, not only by the code that usually checks first.
 */
it('refuses a second full-access row for the same student and course', function () {
    $student = User::factory()->create();
    $course = Course::factory()->create();

    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'module_id' => null]);

    expect(fn () => Enrollment::factory()->create([
        'user_id' => $student->id, 'course_id' => $course->id, 'module_id' => null,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('settles a second full-course order onto the access already held', function () {
    $student = User::factory()->create();
    $course = Course::factory()->create(['status' => 'published']);

    // Two checkouts started in parallel, both paid.
    $first = Order::factory()->for($student)->create(['course_id' => $course->id, 'scope' => 'full', 'status' => 'pending']);
    $second = Order::factory()->for($student)->create(['course_id' => $course->id, 'scope' => 'full', 'status' => 'pending']);

    expect($first->markPaidAndEnroll())->toBeTrue()
        ->and($second->markPaidAndEnroll())->toBeTrue();

    // Both payments are recorded; access is one row, the first order's.
    expect(Enrollment::query()->where('user_id', $student->id)->count())->toBe(1)
        ->and(Enrollment::query()->where('user_id', $student->id)->value('order_id'))->toBe($first->id);
});
