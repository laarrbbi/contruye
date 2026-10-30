<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Go-live spec, user story 3: an order awaiting review for more than 48 hours
 * is visible at a glance. The buyer has already sent the money.
 */
it('records when the buyer sent the receipt', function () {
    Storage::fake('local');
    $this->travelTo(now()->startOfMinute());

    $order = Order::factory()->create(['method' => 'manual_transfer', 'status' => 'pending']);

    $this->actingAs($order->user)
        ->post("/api/orders/{$order->id}/receipt", ['receipt' => UploadedFile::fake()->image('recu.jpg')])
        ->assertOk();

    expect($order->fresh()->receipt_uploaded_at?->equalTo(now()))->toBeTrue();
});

it('counts receipts that have waited more than 48 hours on the dashboard', function () {
    $admin = User::factory()->admin()->create();

    Order::factory()->awaitingReview()->create(['receipt_uploaded_at' => now()->subHours(49)]);
    Order::factory()->awaitingReview()->create(['receipt_uploaded_at' => now()->subHours(72)]);
    Order::factory()->awaitingReview()->create(['receipt_uploaded_at' => now()->subHours(47)]);
    // Paid long ago: reviewed, not waiting.
    Order::factory()->paid()->create(['receipt_uploaded_at' => now()->subDays(10)]);

    $this->actingAs($admin)->getJson('/api/admin/stats')
        ->assertOk()
        ->assertJsonPath('data.orders_awaiting_review', 3)
        ->assertJsonPath('data.orders_awaiting_review_overdue', 2);
});

it('lists receipts to review oldest first, with how long each has waited', function () {
    $admin = User::factory()->admin()->create();

    $recent = Order::factory()->awaitingReview()->create(['receipt_uploaded_at' => now()->subHour()]);
    $oldest = Order::factory()->awaitingReview()->create(['receipt_uploaded_at' => now()->subDays(3)]);
    $middle = Order::factory()->awaitingReview()->create(['receipt_uploaded_at' => now()->subDay()]);

    $rows = $this->actingAs($admin)->getJson('/api/admin/orders?status=awaiting_review')
        ->assertOk()
        ->json('data');

    expect(array_column($rows, 'id'))->toBe([$oldest->id, $middle->id, $recent->id])
        ->and($rows[0]['receipt_uploaded_at'])->toBe($oldest->receipt_uploaded_at->toIso8601String());
});
