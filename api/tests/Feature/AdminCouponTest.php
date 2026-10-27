<?php

use App\Models\Coupon;
use App\Models\User;

it('lists coupons for admins', function () {
    $admin = User::factory()->admin()->create();
    Coupon::factory()->count(2)->create();

    $this->actingAs($admin)
        ->getJson('/api/admin/coupons')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('creates a coupon', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->postJson('/api/admin/coupons', [
            'code' => 'PROMO_25',
            'percent_off' => 25,
            'max_uses' => 50,
            'expires_at' => now()->addMonth()->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'PROMO_25')
        ->assertJsonPath('data.percent_off', 25)
        ->assertJsonPath('data.uses', 0);

    $this->assertDatabaseHas('coupons', ['code' => 'PROMO_25', 'percent_off' => 25]);
});

it('rejects a duplicate coupon code', function () {
    $admin = User::factory()->admin()->create();
    Coupon::factory()->create(['code' => 'DUP10']);

    $this->actingAs($admin)
        ->postJson('/api/admin/coupons', [
            'code' => 'DUP10',
            'percent_off' => 10,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('rejects an out-of-range percent_off', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->postJson('/api/admin/coupons', [
            'code' => 'TOOBIG',
            'percent_off' => 150,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['percent_off']);
});

it('updates a coupon keeping its own code', function () {
    $admin = User::factory()->admin()->create();
    $coupon = Coupon::factory()->create(['code' => 'KEEPME', 'percent_off' => 10]);

    $this->actingAs($admin)
        ->putJson("/api/admin/coupons/{$coupon->id}", [
            'code' => 'KEEPME',
            'percent_off' => 40,
            'max_uses' => null,
            'expires_at' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.percent_off', 40)
        ->assertJsonPath('data.max_uses', null);

    $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'percent_off' => 40]);
});

it('deletes a coupon', function () {
    $admin = User::factory()->admin()->create();
    $coupon = Coupon::factory()->create();

    $this->actingAs($admin)
        ->deleteJson("/api/admin/coupons/{$coupon->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
});
