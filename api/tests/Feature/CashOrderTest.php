<?php

use App\Mail\PaymentConfirmedMail;
use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * C5: cash paid at the office. Priced by PricingService, recorded as a paid
 * `cash` order through markPaidAndEnroll, never a price typed by hand.
 */
beforeEach(fn () => Mail::fake());

it('previews the price without recording anything', function () {
    $student = User::factory()->create();
    $order = paidModulesOrder(User::factory()->create()); // a course with 3 × 49 € modules
    $course = $order->course;

    $this->actingAs(User::factory()->admin()->create())
        ->postJson("/api/admin/students/{$student->id}/cash-orders", [
            'course_id' => $course->id, 'scope' => 'full', 'confirm' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.quote.total', 490)
        ->assertJsonPath('data.quote.scope', 'full');

    expect(Order::query()->where('user_id', $student->id)->exists())->toBeFalse();
    Mail::assertNothingQueued();
});

it('records a paid cash order, enrolls the student and tells them', function () {
    $student = User::factory()->create();
    $course = paidModulesOrder(User::factory()->create())->course;
    $modules = $course->modules()->orderBy('order')->pluck('id');
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->postJson("/api/admin/students/{$student->id}/cash-orders", [
            'course_id' => $course->id,
            'scope' => 'modules',
            'module_ids' => [$modules[0], $modules[1]],
            'confirm' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.method', 'cash')
        ->assertJsonPath('data.status', 'paid')
        // Two modules at 49 €, the course's two-module tier applied by the
        // same rules as checkout — not a number the admin typed.
        ->assertJsonPath('data.pricing_snapshot.subtotal', 98)
        ->assertJsonPath('data.pricing_snapshot.total', 93);

    $order = Order::query()->findOrFail($response->json('data.id'));

    expect($order->amount)->toBe($response->json('data.pricing_snapshot.total'))
        ->and(Enrollment::query()->where('user_id', $student->id)->pluck('module_id')->sort()->values()->all())
        ->toBe([$modules[0], $modules[1]])
        ->and(AuditLog::query()->where('action', 'order.cash_recorded')->where('auditable_id', $order->id)->exists())->toBeTrue();

    Mail::assertQueued(PaymentConfirmedMail::class, fn ($mail) => $mail->hasTo($student->email));
});

it('applies checkout\'s rules: nothing sold twice', function () {
    $student = User::factory()->create();
    $course = paidModulesOrder($student)->course;
    // The student already owns the whole course.
    Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'module_id' => null]);

    $this->actingAs(User::factory()->admin()->create())
        ->postJson("/api/admin/students/{$student->id}/cash-orders", [
            'course_id' => $course->id, 'scope' => 'full', 'confirm' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('course_id');

    expect(Order::query()->where('user_id', $student->id)->where('method', 'cash')->exists())->toBeFalse();
});

it('is for admins, and for students only', function () {
    $student = User::factory()->create();
    $course = paidModulesOrder(User::factory()->create())->course;
    $payload = ['course_id' => $course->id, 'scope' => 'full', 'confirm' => true];

    $this->actingAs($student)->postJson("/api/admin/students/{$student->id}/cash-orders", $payload)->assertForbidden();

    $otherAdmin = User::factory()->admin()->create();
    $this->actingAs(User::factory()->admin()->create())
        ->postJson("/api/admin/students/{$otherAdmin->id}/cash-orders", $payload)
        ->assertNotFound();

    expect(Order::query()->where('method', 'cash')->exists())->toBeFalse();
});

it('shows a per-module buyer as one course, measured over what they own', function () {
    $student = User::factory()->create();
    $course = paidModulesOrder(User::factory()->create())->course;
    $modules = $course->modules()->orderBy('order')->get();
    foreach ($modules as $module) {
        Lesson::factory()->count(2)->create(['module_id' => $module->id]);
    }
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->postJson("/api/admin/students/{$student->id}/cash-orders", [
        'course_id' => $course->id, 'scope' => 'modules',
        'module_ids' => [$modules[0]->id, $modules[1]->id], 'confirm' => true,
    ])->assertCreated();

    $enrollments = $this->actingAs($admin)->getJson("/api/admin/students/{$student->id}")
        ->assertOk()
        ->json('data.enrollments');

    // Two module enrollments, one course row: 2 of 3 modules, 4 lessons owned
    // (not 6) — it used to read as two rows of "0 / 6".
    expect($enrollments)->toHaveCount(1)
        ->and($enrollments[0]['full_access'])->toBeFalse()
        ->and($enrollments[0]['owned_modules_count'])->toBe(2)
        ->and($enrollments[0]['total_modules_count'])->toBe(3)
        ->and($enrollments[0]['total_lessons'])->toBe(4);
});
