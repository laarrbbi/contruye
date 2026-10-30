<?php

use App\Models\AuditLog;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\QueryException;

it('refuses to delete a course that has been sold, and never destroys the order', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create();
    $course = Course::factory()->create(['status' => 'published']);

    $order = Order::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 90000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->actingAs($admin)->deleteJson("/api/admin/courses/{$course->id}")
        ->assertUnprocessable();

    // The record survives — that is the whole point.
    expect(Course::find($course->id))->not->toBeNull()
        ->and(Order::find($order->id))->not->toBeNull();

    // An unsold course still deletes normally.
    $unsold = Course::factory()->create();
    $this->actingAs($admin)->deleteJson("/api/admin/courses/{$unsold->id}")->assertNoContent();
    expect(Course::find($unsold->id))->toBeNull();
});

it('refuses to delete a module that appears on an order line', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create();
    $course = Course::factory()->create();
    $sold = Module::factory()->for($course)->create(['order' => 1]);
    $unsold = Module::factory()->for($course)->create(['order' => 2]);

    $order = Order::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'scope' => 'modules',
        'status' => 'paid',
        'paid_at' => now(),
    ]);
    $order->orderModules()->create(['module_id' => $sold->id, 'unit_price' => 12000]);

    $this->actingAs($admin)->deleteJson("/api/admin/modules/{$sold->id}")
        ->assertUnprocessable();

    expect(Module::find($sold->id))->not->toBeNull()
        ->and($order->fresh()->orderModules()->count())->toBe(1);

    $this->actingAs($admin)->deleteJson("/api/admin/modules/{$unsold->id}")->assertNoContent();
});

it('will not let the database delete a user who has orders', function () {
    $student = User::factory()->create();
    $course = Course::factory()->create();
    Order::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    // The foreign key is the guarantee, independent of any controller.
    expect(fn () => $student->delete())
        ->toThrow(QueryException::class);

    expect(User::find($student->id))->not->toBeNull();
});

it('keeps a module a student has actually paid for, and their access with it', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create();
    $course = Course::factory()->create(['status' => 'published']);
    $module = Module::factory()->for($course)->create(['order' => 1, 'price_eur' => 12000]);
    Lesson::factory()->for($module)->create(['order' => 1]);

    $order = Order::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 12000,
        'scope' => 'modules',
        'method' => 'manual_transfer',
        'status' => 'pending',
    ]);
    $order->orderModules()->create(['module_id' => $module->id, 'unit_price' => 12000]);
    $order->markPaidAndEnroll();

    expect(Enrollment::query()->where('module_id', $module->id)->count())->toBe(1);

    $this->actingAs($admin)->deleteJson("/api/admin/modules/{$module->id}")
        ->assertUnprocessable()
        ->assertJsonStructure(['message']);

    // enrollments.module_id cascades on delete, so a guard that stopped
    // covering a module with live per-module access would revoke what a
    // student paid for, silently and with no way back.
    expect(Module::find($module->id))->not->toBeNull()
        ->and(Enrollment::query()->where('module_id', $module->id)->count())->toBe(1);

    // And the access is not merely a surviving row: the player still opens it.
    $modules = $this->actingAs($student)->getJson("/api/courses/{$course->slug}/learn")
        ->assertOk()
        ->json('data.modules');

    expect(collect($modules)->firstWhere('id', $module->id)['is_locked'])->toBeFalse();
});

it('never lets a deleted coupon take a paid order down with it', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create();
    $course = Course::factory()->create(['status' => 'published', 'price_eur' => 49900]);
    $coupon = Coupon::factory()->create(['code' => 'LANCEMENT20', 'percent_off' => 20]);

    $orderId = $this->actingAs($student)->postJson('/api/checkout', [
        'course_id' => $course->id,
        'method' => 'manual_transfer',
        'coupon_code' => 'LANCEMENT20',
    ])->assertCreated()->json('data.order.id');

    Order::find($orderId)->markPaidAndEnroll();

    $this->actingAs($admin)->deleteJson("/api/admin/coupons/{$coupon->id}")->assertNoContent();

    $order = Order::find($orderId);

    // orders.coupon_id is nullOnDelete: what is lost is the LINK, never the
    // financial record. The amount charged, the paid status and the access it
    // delivered all have to survive the coupon's removal.
    expect($order)->not->toBeNull()
        ->and($order->amount)->toBe(39920)
        ->and($order->status)->toBe('paid')
        ->and($order->coupon_id)->toBeNull()
        ->and(Enrollment::query()->where('order_id', $orderId)->count())->toBe(1);

    // Which is exactly why the trail has to carry the code and its counter:
    // once the row is gone it is the only remaining answer to "why did this
    // order cost 39 920 instead of 49 900?".
    $entry = AuditLog::query()->where('action', 'coupon.deleted')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->user_id)->toBe($admin->id)
        ->and($entry->meta['code'])->toBe('LANCEMENT20')
        ->and($entry->meta['uses'])->toBe(1);
});
