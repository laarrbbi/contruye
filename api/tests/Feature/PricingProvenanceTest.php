<?php

use App\Models\Coupon;
use App\Models\Course;
use App\Models\Order;
use App\Models\User;

it('keeps every term that produced the price, so a paid order can explain itself', function () {
    $course = Course::factory()->create(['status' => 'published', 'price_dzd' => 100000]);
    $coupon = Coupon::factory()->create(['code' => 'PROV20', 'percent_off' => 20, 'max_uses' => null]);
    $user = User::factory()->create();

    $orderId = $this->actingAs($user)->postJson('/api/checkout', [
        'course_id' => $course->id,
        'method' => 'manual_ccp',
        'coupon_code' => $coupon->code,
    ])->assertCreated()->json('data.order.id');

    $snapshot = Order::find($orderId)->pricing_snapshot;

    expect($snapshot)->not->toBeNull()
        ->and($snapshot['coupon_code'])->toBe('PROV20')
        ->and($snapshot['coupon_percent'])->toBe(20)
        ->and($snapshot['currency'])->toBe('DZD')
        // The terms must actually add up to what was charged.
        ->and($snapshot['total'])->toBe(Order::find($orderId)->amount)
        ->and($snapshot['subtotal'] - $snapshot['tier_discount'] - $snapshot['campaign_discount'] - $snapshot['coupon_discount'])
        ->toBe($snapshot['total']);
});

it('survives the coupon being deleted afterwards', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $course = Course::factory()->create(['status' => 'published', 'price_dzd' => 49900]);
    $coupon = Coupon::factory()->create(['code' => 'GONE20', 'percent_off' => 20, 'max_uses' => null]);

    $orderId = $this->actingAs(User::factory()->create())->postJson('/api/checkout', [
        'course_id' => $course->id,
        'method' => 'manual_ccp',
        'coupon_code' => $coupon->code,
    ])->assertCreated()->json('data.order.id');

    $order = Order::find($orderId);
    $order->markPaidAndEnroll();
    $chargedAmount = $order->fresh()->amount;

    // The admin removes the campaign's coupon months later.
    $this->actingAs($admin)->deleteJson("/api/admin/coupons/{$coupon->id}")->assertNoContent();

    $order = Order::find($orderId);

    // The link is gone — that is what ON DELETE SET NULL does — but the
    // reason the order cost what it cost is still on the order itself.
    expect($order->coupon_id)->toBeNull()
        ->and($order->amount)->toBe($chargedAmount)
        ->and($order->pricing_snapshot['coupon_code'])->toBe('GONE20')
        ->and($order->pricing_snapshot['coupon_percent'])->toBe(20);
});

it('shows the admin how a price was reached', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $course = Course::factory()->create(['status' => 'published', 'price_dzd' => 90000]);

    // A plain full-access order, no promotions.
    $orderId = $this->actingAs(User::factory()->create())->postJson('/api/checkout', [
        'course_id' => $course->id,
        'method' => 'manual_baridimob',
    ])->assertCreated()->json('data.order.id');

    $snapshot = $this->actingAs($admin)->getJson("/api/admin/orders/{$orderId}")
        ->assertOk()->json('data.pricing_snapshot');

    expect($snapshot)->not->toBeNull()
        ->and($snapshot['coupon_code'])->toBeNull()
        ->and($snapshot['total'])->toBe(90000);
});
