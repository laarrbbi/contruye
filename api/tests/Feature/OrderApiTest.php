<?php

use App\Models\Order;
use App\Models\User;

it('requires authentication', function () {
    $this->getJson('/api/orders')->assertUnauthorized();
});

it('lists only the user\'s own orders, newest first', function () {
    $user = User::factory()->create();
    $older = Order::factory()->for($user)->create(['created_at' => now()->subDay()]);
    $newer = Order::factory()->for($user)->create(['created_at' => now()]);
    Order::factory()->create(); // someone else's

    $this->actingAs($user)
        ->getJson('/api/orders')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id);
});

it('shows a manual pending order with payment instructions', function () {
    config([
        'services.manual_payment.ccp_number' => '1234567 89',
        'services.manual_payment.ccp_name' => 'CONSTRUYE ACADEMY SARL',
        'services.manual_payment.baridimob_rip' => '00799999000000000000',
    ]);

    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create([
        'method' => 'manual_ccp',
        'status' => 'pending',
        'amount' => 45000,
    ]);

    $this->actingAs($user)
        ->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.has_receipt', false)
        ->assertJsonPath('data.payment_instructions.method', 'manual_ccp')
        ->assertJsonPath('data.payment_instructions.amount', 45000)
        ->assertJsonPath('data.payment_instructions.currency', 'DZD')
        ->assertJsonPath('data.payment_instructions.ccp_number', '1234567 89')
        ->assertJsonPath('data.payment_instructions.ccp_name', 'CONSTRUYE ACADEMY SARL')
        ->assertJsonPath('data.payment_instructions.baridimob_rip', '00799999000000000000');
});

it('answers someone else\'s order exactly like one that does not exist', function () {
    $order = Order::factory()->create();
    $intruder = User::factory()->create();

    // S4/S9/C37: a 403 here told any account which sequential ids exist.
    $theirs = $this->actingAs($intruder)->getJson("/api/orders/{$order->id}")->assertNotFound();
    $missing = $this->actingAs($intruder)->getJson('/api/orders/999999')->assertNotFound();

    expect($theirs->json())->toBe($missing->json());
});

it('lets an admin view any order', function () {
    $order = Order::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $order->id);
});

it('omits payment instructions for chargily orders', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->chargily()->create();

    $this->actingAs($user)
        ->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.method', 'chargily')
        ->assertJsonMissingPath('data.payment_instructions');
});

it('omits payment instructions once a manual order is paid', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->paid()->create(['method' => 'manual_ccp']);

    $this->actingAs($user)
        ->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.payment_instructions');
});
