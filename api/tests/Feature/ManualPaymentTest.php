<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function fakeReceiptFile(string $name = 'receipt.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< >>\n%%EOF\n",
    );
}

beforeEach(function () {
    Storage::fake('local');
});

it('stores the receipt privately and moves the order to awaiting_review', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['method' => 'manual_transfer', 'status' => 'pending']);

    $this->actingAs($user)
        ->post("/api/orders/{$order->id}/receipt", ['receipt' => fakeReceiptFile()], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', 'awaiting_review')
        ->assertJsonPath('data.has_receipt', true);

    $order->refresh();

    expect($order->status)->toBe('awaiting_review')
        ->and($order->receipt_path)->toStartWith("receipts/{$order->id}/");

    Storage::disk('local')->assertExists($order->receipt_path);
});

it('rejects files over 5MB', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['method' => 'manual_transfer', 'status' => 'pending']);

    $file = UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf');

    $this->actingAs($user)
        ->post("/api/orders/{$order->id}/receipt", ['receipt' => $file], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['receipt']);

    expect($order->fresh()->status)->toBe('pending');
});

it('rejects unsupported file types', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['method' => 'manual_transfer', 'status' => 'pending']);

    $file = UploadedFile::fake()->createWithContent('receipt.gif', 'GIF89a-not-allowed');

    $this->actingAs($user)
        ->post("/api/orders/{$order->id}/receipt", ['receipt' => $file], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['receipt']);
});

it('refuses a receipt on someone else\'s order as if it did not exist', function () {
    $order = Order::factory()->create(['method' => 'manual_transfer', 'status' => 'pending']);
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->post("/api/orders/{$order->id}/receipt", ['receipt' => fakeReceiptFile()], ['Accept' => 'application/json'])
        ->assertNotFound();

    expect($order->fresh()->receipt_path)->toBeNull();
});

it('rejects receipt uploads on non-manual orders', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->stripe()->create();

    $this->actingAs($user)
        ->post("/api/orders/{$order->id}/receipt", ['receipt' => fakeReceiptFile()], ['Accept' => 'application/json'])
        ->assertUnprocessable();
});

it('rejects receipt uploads once the order is paid', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->paid()->create(['method' => 'manual_transfer']);

    $this->actingAs($user)
        ->post("/api/orders/{$order->id}/receipt", ['receipt' => fakeReceiptFile()], ['Accept' => 'application/json'])
        ->assertUnprocessable();
});

it('replaces the old receipt after a rejection and returns to awaiting_review', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['method' => 'manual_transfer', 'status' => 'pending']);

    $oldPath = Storage::disk('local')->putFile("receipts/{$order->id}", fakeReceiptFile('old.pdf'));
    $order->update([
        'receipt_path' => $oldPath,
        'status' => 'failed',
        'admin_note' => 'Photo is unreadable',
    ]);

    $this->actingAs($user)
        ->post("/api/orders/{$order->id}/receipt", ['receipt' => fakeReceiptFile('new.pdf')], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', 'awaiting_review')
        ->assertJsonPath('data.admin_note', null);

    $order->refresh();

    expect($order->receipt_path)->not->toBe($oldPath)
        ->and($order->admin_note)->toBeNull();

    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($order->receipt_path);
});

it('keeps the previous receipt when the new one cannot be stored — C24', function () {
    Storage::fake('local');
    $buyer = User::factory()->create();
    $order = Order::factory()->for($buyer)->create(['method' => 'manual_transfer', 'status' => 'pending']);

    $this->actingAs($buyer)
        ->post("/api/orders/{$order->id}/receipt", ['receipt' => fakeReceiptFile()], ['Accept' => 'application/json'])
        ->assertOk();
    $kept = $order->fresh()->receipt_path;

    // The disk refuses the next write (full, unwritable): putFile() answers false.
    $real = Storage::disk('local');
    $failing = Mockery::mock($real)->makePartial();
    $failing->shouldReceive('putFile')->andReturn(false);
    Storage::set('local', $failing);

    $this->actingAs($buyer)
        ->post("/api/orders/{$order->id}/receipt", ['receipt' => fakeReceiptFile()], ['Accept' => 'application/json'])
        ->assertStatus(500)
        ->assertJsonPath('message', __('The receipt could not be saved. Please try again.'));

    // The order still points at the first receipt, and that file still exists.
    expect($order->fresh()->receipt_path)->toBe($kept);
    $real->assertExists($kept);
});
