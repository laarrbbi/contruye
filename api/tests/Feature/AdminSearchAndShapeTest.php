<?php

use App\Models\Order;
use App\Models\User;

/**
 * C51: an admin search matches what was typed, not SQL wildcards.
 * C32: orders_by_method is a JSON object even before the first order.
 */
it('matches the typed text literally, wildcards included', function (string $endpoint) {
    $admin = User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@construye.test']);
    $percent = User::factory()->create(['name' => 'Remise 100% Karim', 'email' => 'karim@construye.test']);
    $plain = User::factory()->create(['name' => 'Amel Cherif', 'email' => 'amel@construye.test']);
    Order::factory()->for($percent)->create();
    Order::factory()->for($plain)->create();

    // Unescaped, "%" matched every row and "_" any single character.
    $byPercent = $this->actingAs($admin)->getJson($endpoint.'?search='.urlencode('%'))->assertOk()->json('data');
    $byUnderscore = $this->actingAs($admin)->getJson($endpoint.'?search='.urlencode('_'))->assertOk()->json('data');

    expect($byPercent)->toHaveCount(1)
        ->and($byUnderscore)->toHaveCount(0);
})->with(['orders' => '/api/admin/orders', 'students' => '/api/admin/students']);

it('reports orders by method as an object even with no orders yet', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->getJson('/api/admin/stats')->assertOk();

    expect($response->getContent())->toContain('"orders_by_method":{}');
});
