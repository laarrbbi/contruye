<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * Migration 2026_08_18_100001 turned four foreign keys from CASCADE to
 * RESTRICT so that no catalogue edit can destroy a payment record.
 *
 * FinancialRecordProtectionTest pins the admin controllers' 422 guards, but a
 * guard returns before any DELETE is issued — revert the migration and every
 * one of those tests still passes. These go straight to the database, the way
 * a tinker session, a seeder, a bulk cleanup or a cascade arriving from
 * another table would, and pin the three constraints nothing else exercises.
 */

/** A paid order for a fresh course, with its own buyer. */
function soldCourse(): Order
{
    return Order::factory()->create([
        'user_id' => User::factory()->create()->id,
        'course_id' => Course::factory()->create(['status' => 'published'])->id,
        'amount' => 90000,
        'currency' => 'EUR',
        'method' => 'manual_transfer',
        'scope' => 'full',
        'status' => 'paid',
        'paid_at' => now(),
    ]);
}

it('will not let the database delete a course that has been sold', function () {
    $order = soldCourse();
    $course = $order->course;

    // orders.course_id — no controller involved: the constraint is the
    // guarantee. Under the old CASCADE this call deleted the paid order.
    expect(fn () => $course->delete())->toThrow(QueryException::class);

    expect(Course::find($course->id))->not->toBeNull()
        ->and(Order::find($order->id))->not->toBeNull()
        ->and(Order::find($order->id)->status)->toBe('paid');
});

it('will not let the database delete a course a student is enrolled in', function () {
    $course = Course::factory()->create(['status' => 'published']);
    $enrollment = Enrollment::factory()->for(User::factory())->for($course)->create();

    // enrollments.course_id, with no order in play at all: a course seeded by
    // mistake and "cleaned up" would otherwise revoke lifetime access rows
    // that were paid for through some other record.
    expect(fn () => $course->delete())->toThrow(QueryException::class);

    expect(Course::find($course->id))->not->toBeNull()
        ->and(Enrollment::find($enrollment->id))->not->toBeNull();
});

it('will not let the database delete a module that appears on an order line', function () {
    $order = soldCourse();
    $module = Module::factory()->for($order->course)->create(['order' => 1]);
    $order->update(['scope' => 'modules']);
    $line = $order->orderModules()->create(['module_id' => $module->id, 'unit_price' => 12000]);

    // order_modules.module_id — the line items an issued invoice is built
    // from. Losing them turns a numbered fiscal document into a total with no
    // designations behind it.
    expect(fn () => $module->delete())->toThrow(QueryException::class);

    expect(Module::find($module->id))->not->toBeNull()
        ->and($order->fresh()->orderModules()->whereKey($line->id)->exists())->toBeTrue();
});

it('still deletes catalogue rows that nobody has paid for', function () {
    // The constraints must refuse payment records and nothing else, or the
    // admin can never remove a course drafted by mistake.
    $unsold = Course::factory()->draft()->create();
    $module = Module::factory()->for($unsold)->create(['order' => 1]);

    $module->delete();
    $unsold->delete();

    expect(Course::find($unsold->id))->toBeNull()
        ->and(Module::find($module->id))->toBeNull();
});
