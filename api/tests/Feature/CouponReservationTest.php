<?php

use App\Models\Coupon;
use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * A limited coupon claims its use at CHECKOUT and hands it back when the order
 * fails. CheckoutTest pins those two halves in isolation; what follows pins
 * what happens AFTER the release — the paths that put a released order back
 * into circulation without re-claiming anything.
 */

/** Check out with the coupon and return the new order. */
function checkoutWithCoupon(Coupon $coupon, Course $course, ?User $buyer = null): Order
{
    $id = test()->actingAs($buyer ?? User::factory()->create())
        ->postJson('/api/checkout', [
            'course_id' => $course->id,
            'method' => 'manual_ccp',
            'coupon_code' => $coupon->code,
        ])->assertCreated()->json('data.order.id');

    return Order::findOrFail($id);
}

it('releases the coupon use as soon as an admin rejects the receipt', function () {
    Mail::fake();

    $course = Course::factory()->create(['price_dzd' => 10000]);
    $coupon = Coupon::factory()->create(['percent_off' => 50, 'max_uses' => 1, 'uses' => 0]);
    $admin = User::factory()->admin()->create();

    $order = checkoutWithCoupon($coupon, $course);
    $order->update(['status' => 'awaiting_review', 'receipt_path' => 'receipts/x.jpg']);

    expect($coupon->fresh()->uses)->toBe(1);

    $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/reject", ['reason' => 'Reçu illisible'])
        ->assertOk();

    // The buyer walked away with nothing, so the coupon is free again.
    expect($order->fresh()->status)->toBe('failed')
        ->and($coupon->fresh()->uses)->toBe(0);
});

it('re-claims the use when a rejected order is granted after someone else took the last use', function () {
    Mail::fake();

    $course = Course::factory()->create(['price_dzd' => 10000]);
    $coupon = Coupon::factory()->create(['percent_off' => 50, 'max_uses' => 1, 'uses' => 0]);
    $admin = User::factory()->admin()->create();

    $first = checkoutWithCoupon($coupon, $course);
    $first->update(['status' => 'awaiting_review', 'receipt_path' => 'receipts/x.jpg']);

    $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$first->id}/reject", ['reason' => 'Reçu illisible'])
        ->assertOk();

    // The released use is taken by a second buyer, legitimately.
    $second = checkoutWithCoupon($coupon, Course::factory()->create(['price_dzd' => 10000]));

    expect($coupon->fresh()->uses)->toBe(1)
        ->and($second->amount)->toBe(5000);

    // The admin changes their mind about the first, FAILED order — the buyer
    // came to the office with the cash — and grants it. The grant stands (the
    // money is in hand) and the use is counted again: the counter now says
    // two discounted orders hold this coupon, which is the truth, one over
    // its cap. It used to read 1 for two orders.
    $this->actingAs($admin)->postJson("/api/admin/orders/{$first->id}/grant")->assertOk();

    expect($first->fresh()->status)->toBe('paid')
        ->and($first->fresh()->amount)->toBe(5000)
        ->and($coupon->fresh()->uses)->toBe(2)
        ->and(Order::query()->where('coupon_id', $coupon->id)->count())->toBe(2);
});

it('re-claims the use when a receipt re-sent after a rejection revives the order', function () {
    Storage::fake('local');

    $course = Course::factory()->create(['price_dzd' => 10000]);
    $coupon = Coupon::factory()->create(['percent_off' => 50, 'max_uses' => 1, 'uses' => 0]);
    $buyer = User::factory()->create();

    $order = checkoutWithCoupon($coupon, $course, $buyer);
    $order->markFailed();

    expect($coupon->fresh()->uses)->toBe(0);

    // UploadReceiptRequest accepts a 'failed' order, so the buyer can send a
    // clearer photo of the same transfer.
    $this->actingAs($buyer)->postJson("/api/orders/{$order->id}/receipt", [
        'receipt' => UploadedFile::fake()->image('recu.jpg'),
    ])->assertOk()->assertJsonPath('data.status', 'awaiting_review');

    // Back in the review queue at the discounted amount, holding its use.
    expect($order->fresh()->status)->toBe('awaiting_review')
        ->and($order->fresh()->amount)->toBe(5000)
        ->and($coupon->fresh()->uses)->toBe(1);

    $order->fresh()->markPaidAndEnroll();

    // Paid from awaiting_review: no second claim.
    expect($order->fresh()->status)->toBe('paid')
        ->and($coupon->fresh()->uses)->toBe(1);
});

it('never lets the released counter fall below zero, however many times an order fails', function () {
    $course = Course::factory()->create(['price_dzd' => 10000]);
    $coupon = Coupon::factory()->create(['percent_off' => 50, 'max_uses' => 3, 'uses' => 0]);

    $order = checkoutWithCoupon($coupon, $course);

    expect($coupon->fresh()->uses)->toBe(1);

    // A retried webhook, a rejected receipt and an expiry can all land on the
    // same order. An unsigned counter that wrapped would hand out an
    // unlimited number of discounts.
    $order->markFailed();
    $order->fresh()->markFailed();
    $order->fresh()->markFailed();

    expect($coupon->fresh()->uses)->toBe(0);
});

it('keeps the reserved use when the order is already paid', function () {
    $course = Course::factory()->create(['price_dzd' => 10000]);
    $coupon = Coupon::factory()->create(['percent_off' => 50, 'max_uses' => 1, 'uses' => 0]);

    $order = checkoutWithCoupon($coupon, $course);
    $order->markPaidAndEnroll();

    // A late checkout.failed / checkout.session.expired for an order that
    // already settled must not release a use the buyer genuinely spent.
    $order->fresh()->markFailed();

    expect($order->fresh()->status)->toBe('paid')
        ->and($coupon->fresh()->uses)->toBe(1);
});
