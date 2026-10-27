<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * The smallest course that can be pushed through every endpoint reading an
 * enrollment: one module, one lesson, one attachment.
 *
 * @return array{0: Course, 1: Module, 2: Lesson}
 */
function enrollmentGateLesson(): array
{
    $course = Course::factory()->create(['status' => 'published', 'price_dzd' => 90000]);
    $module = Module::factory()->for($course)->create(['order' => 1, 'price_dzd' => 12000]);
    $lesson = Lesson::factory()->for($module)->create([
        'order' => 1,
        'bunny_video_id' => 'vid-enrollment-1',
        'content_es' => 'Contenu payant.',
        'key_points_es' => ['Point payant'],
        'attachments' => [['name' => 'Fiche.pdf', 'path' => 'lessons/attachments/fiche.pdf']],
        // So the reflex-sheet gate is exercised too: only flagged lessons have one.
        'is_reflex_sheet' => true,
    ]);

    return [$course, $module, $lesson];
}

it('shuts every gate the moment an enrollment expires', function () {
    config([
        'services.bunny.library_id' => '123456',
        'services.bunny.token_key' => 'test-token-key',
        'services.bunny.embed_ttl' => 14400,
    ]);
    Storage::fake('local');
    Storage::disk('local')->put('lessons/attachments/fiche.pdf', 'paid attachment body');

    [$course, $module, $lesson] = enrollmentGateLesson();
    $student = User::factory()->create();

    $enrollment = Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => null,
        'expires_at' => now()->addYear(),
    ]);

    // While the access is live, every endpoint answers.
    $this->actingAs($student)->getJson('/api/enrollments')->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($student)->getJson("/api/courses/{$course->slug}/learn")->assertOk();
    $this->actingAs($student)->getJson("/api/courses/{$course->slug}/checklists")->assertOk();
    $this->actingAs($student)->getJson("/api/lessons/{$lesson->id}/playback")->assertOk();
    $this->actingAs($student)->postJson("/api/lessons/{$lesson->id}/progress", ['position_seconds' => 12])->assertOk();
    $this->actingAs($student)->postJson("/api/lessons/{$lesson->id}/complete")->assertOk();
    $this->actingAs($student)->get("/api/lessons/{$lesson->id}/attachments/0")->assertOk();
    $this->actingAs($student)->get("/api/lessons/{$lesson->id}/reflex-sheet")->assertOk();

    // The access lapses. The row is still there and the dashboard filtering it
    // out is not enough — the same predicate has to close the player, the
    // video, the progress writes, the files and the checklists at once.
    $enrollment->update(['expires_at' => now()->subSecond()]);

    $this->actingAs($student)->getJson('/api/enrollments')->assertOk()->assertJsonCount(0, 'data');
    $this->actingAs($student)->getJson("/api/courses/{$course->slug}/learn")->assertForbidden();
    $this->actingAs($student)->getJson("/api/courses/{$course->slug}/checklists")->assertForbidden();
    $this->actingAs($student)->getJson("/api/lessons/{$lesson->id}/playback")->assertForbidden();
    $this->actingAs($student)->postJson("/api/lessons/{$lesson->id}/progress", ['position_seconds' => 30])->assertForbidden();
    $this->actingAs($student)->postJson("/api/lessons/{$lesson->id}/complete")->assertForbidden();
    $this->actingAs($student)->getJson("/api/lessons/{$lesson->id}/attachments/0")->assertForbidden();
    $this->actingAs($student)->getJson("/api/lessons/{$lesson->id}/reflex-sheet")->assertForbidden();
});

it('treats the expiry instant as expired and one second earlier as live', function () {
    // A whole second, so the value written to a second-precision timestamp
    // column is exactly the instant the predicate compares against.
    $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC'));

    [$course, $module] = enrollmentGateLesson();
    $student = User::factory()->create();

    $enrollment = Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => $module->id,
        'expires_at' => now()->addSecond(),
    ]);

    // Strictly greater than now: one second of life left is still access.
    $this->actingAs($student)->getJson("/api/courses/{$course->slug}/learn")->assertOk();

    // On the stroke of the deadline it is over — the boundary is exclusive.
    $enrollment->update(['expires_at' => now()]);
    $this->actingAs($student)->getJson("/api/courses/{$course->slug}/learn")->assertForbidden();

    $this->travelBack();
});

it('lets an expired student buy again instead of claiming they already own it', function () {
    [$course] = enrollmentGateLesson();
    $student = User::factory()->create();

    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'module_id' => null,
        'expires_at' => now()->subDay(),
    ]);

    // The mirror image of the lockout: whatever stops an expired student
    // watching must also stop counting them as an owner, or they are refused
    // the renewal that would fix it.
    $this->actingAs($student)->postJson('/api/checkout', [
        'course_id' => $course->id,
        'method' => 'manual_ccp',
        'scope' => 'full',
    ])->assertCreated()->assertJsonPath('data.order.amount', 90000);

    // And a live enrollment still refuses the duplicate purchase.
    Enrollment::query()->where('user_id', $student->id)->update(['expires_at' => null]);

    $this->actingAs($student)->postJson('/api/checkout', [
        'course_id' => $course->id,
        'method' => 'manual_ccp',
        'scope' => 'full',
    ])->assertUnprocessable()->assertJsonValidationErrors('course_id');
});

it('opens no second access row when a second paid order delivers the same course', function () {
    [$course, $module] = enrollmentGateLesson();
    $student = User::factory()->create();

    $orders = collect([1, 2])->map(fn () => Order::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 90000,
        'scope' => 'full',
        'method' => 'manual_ccp',
        'status' => 'pending',
    ]));

    // Both are genuine, separately-placed orders — not a re-delivery of one.
    expect($orders[0]->markPaidAndEnroll())->toBeTrue()
        ->and($orders[1]->markPaidAndEnroll())->toBeTrue();

    $rows = Enrollment::query()
        ->where('user_id', $student->id)
        ->where('course_id', $course->id)
        ->get();

    // MySQL repeats NULLs happily inside a unique index, so (user, course,
    // NULL) is guarded by firstOrCreate alone. Two full-access rows would make
    // hasFullAccessTo, the dashboard and the revocation path disagree.
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->module_id)->toBeNull()
        // Attributed to the order that actually opened the access.
        ->and($rows->first()->order_id)->toBe($orders[0]->id);

    // Both orders remain paid: the guard is on access, not on the ledger.
    expect($orders[0]->fresh()->status)->toBe('paid')
        ->and($orders[1]->fresh()->status)->toBe('paid');

    // The same holds à la carte, where the unique index does bite: two orders
    // for the same module must not raise a duplicate-key 500 on delivery.
    $buyer = User::factory()->create();

    $perModule = collect([1, 2])->map(function () use ($buyer, $course, $module) {
        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'course_id' => $course->id,
            'amount' => 12000,
            'scope' => 'modules',
            'method' => 'manual_ccp',
            'status' => 'pending',
        ]);
        $order->orderModules()->create(['module_id' => $module->id, 'unit_price' => 12000]);

        return $order;
    });

    $perModule->each(fn (Order $order) => $order->markPaidAndEnroll());

    expect(Enrollment::query()->where('user_id', $buyer->id)->where('module_id', $module->id)->count())->toBe(1)
        ->and($perModule[1]->fresh()->status)->toBe('paid');
});
