<?php

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A capped coupon's `uses` must equal the orders that hold it: reserved at
 * checkout, given back once when the payment fails, taken again if the
 * order is paid after all.
 */
function couponOrder(string $method = 'chargily', string $status = 'pending'): array
{
    $coupon = Coupon::factory()->create(['code' => 'BLED50', 'percent_off' => 50, 'max_uses' => 10, 'uses' => 3]);
    $order = Order::factory()->create([
        'coupon_id' => $coupon->id,
        'method' => $method,
        'status' => $status,
        'amount' => 45000,
        'currency' => 'DZD',
    ]);

    return [$coupon, $order];
}

it('gives the use back once, however many times the failure is reported', function () {
    [$coupon, $order] = couponOrder();
    config(['services.chargily.secret' => 'chargily_real_secret']);

    $body = json_encode([
        'type' => 'checkout.failed',
        'data' => ['metadata' => ['order_id' => $order->id]],
    ]);

    // Gateways re-deliver; an attacker holding one signed event can replay it.
    foreach (range(1, 4) as $delivery) {
        $this->call('POST', '/api/webhooks/chargily', [], [], [], [
            'HTTP_SIGNATURE' => hash_hmac('sha256', $body, 'chargily_real_secret'),
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();
    }

    expect($order->fresh()->status)->toBe('failed')
        ->and($coupon->fresh()->uses)->toBe(2);
});

it('does not release a use from an order that is already paid or refunded', function (string $status) {
    [$coupon, $order] = couponOrder(status: $status);

    $order->markFailed();

    expect($order->fresh()->status)->toBe($status)
        ->and($coupon->fresh()->uses)->toBe(3);
})->with(['paid', 'refunded']);

it('counts the use again when a failed order is paid after all', function () {
    // A CCP receipt was rejected (use released), the buyer sent a new one,
    // and the admin approves it: the order is paid at the coupon price.
    [$coupon, $order] = couponOrder('manual_ccp');
    $order->markFailed();
    expect($coupon->fresh()->uses)->toBe(2);

    Storage::fake('local');
    $this->actingAs($order->user)
        ->post("/api/orders/{$order->id}/receipt", ['receipt' => UploadedFile::fake()->image('nouveau-recu.jpg')])
        ->assertOk();
    expect($coupon->fresh()->uses)->toBe(3);

    $this->actingAs(User::factory()->admin()->create())
        ->postJson("/api/admin/orders/{$order->id}/approve")
        ->assertOk();

    expect($order->fresh()->status)->toBe('paid')
        ->and($coupon->fresh()->uses)->toBe(3);
});

it('counts the use on a late confirmation even when the cap has since been reached', function () {
    [$coupon, $order] = couponOrder();
    $order->markFailed();
    $coupon->update(['uses' => 10]); // others took the remaining uses meanwhile

    // The money arrived: the payment stands and the record shows the use.
    expect($order->markPaidAndEnroll())->toBeTrue()
        ->and($coupon->fresh()->uses)->toBe(11);
});
