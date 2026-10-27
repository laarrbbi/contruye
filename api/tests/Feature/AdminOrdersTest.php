<?php

use App\Mail\PaymentConfirmedMail;
use App\Mail\ReceiptRejectedMail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

it('forbids non-admins on every admin order route', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create();

    $this->actingAs($user)->getJson('/api/admin/orders')->assertForbidden();
    $this->actingAs($user)->getJson("/api/admin/orders/{$order->id}")->assertForbidden();
    $this->actingAs($user)->getJson("/api/admin/orders/{$order->id}/receipt")->assertForbidden();
    $this->actingAs($user)->postJson("/api/admin/orders/{$order->id}/approve")->assertForbidden();
    $this->actingAs($user)->postJson("/api/admin/orders/{$order->id}/reject", ['reason' => 'x'])->assertForbidden();
    $this->actingAs($user)->postJson("/api/admin/orders/{$order->id}/grant")->assertForbidden();
});

it('filters orders by status, method and user search', function () {
    $admin = User::factory()->admin()->create();
    $alice = User::factory()->create(['name' => 'Alice Wonder', 'email' => 'alice@example.com']);
    $bob = User::factory()->create(['name' => 'Bob Builder', 'email' => 'bob@example.com']);

    Order::factory()->for($alice)->paid()->create(['method' => 'chargily']);
    Order::factory()->for($bob)->create(['method' => 'manual_ccp', 'status' => 'pending']);
    Order::factory()->for($bob)->awaitingReview()->create(); // manual_ccp

    $this->actingAs($admin)->getJson('/api/admin/orders')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data', 'links', 'meta']);

    $this->actingAs($admin)->getJson('/api/admin/orders?status=paid')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user.email', 'alice@example.com')
        ->assertJsonPath('data.0.status', 'paid');

    $this->actingAs($admin)->getJson('/api/admin/orders?method=manual_ccp')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->actingAs($admin)->getJson('/api/admin/orders?search=alice')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user.name', 'Alice Wonder');

    $this->actingAs($admin)->getJson('/api/admin/orders?search=Builder')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->actingAs($admin)->getJson('/api/admin/orders?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3);
});

it('approves an awaiting_review order: paid, enrolled, audited, mailed', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $order = Order::factory()->awaitingReview()->create();

    $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'paid');

    $order->refresh();

    expect($order->status)->toBe('paid')
        ->and($order->paid_at)->not->toBeNull();

    $this->assertDatabaseHas('enrollments', [
        'user_id' => $order->user_id,
        'course_id' => $order->course_id,
        'order_id' => $order->id,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $admin->id,
        'action' => 'order.approved',
        'auditable_type' => Order::class,
        'auditable_id' => $order->id,
    ]);

    Mail::assertQueued(PaymentConfirmedMail::class, fn ($mail) => $mail->hasTo($order->user->email));
});

it('rejects approving an order that is not awaiting review', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $order = Order::factory()->create(['status' => 'pending']);

    $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/approve")
        ->assertUnprocessable();

    expect($order->fresh()->status)->toBe('pending');
    $this->assertDatabaseCount('enrollments', 0);
    Mail::assertNothingQueued();
});

it('rejects an awaiting_review order with a reason: failed, audited, mailed', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $order = Order::factory()->awaitingReview()->create();

    $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/reject", ['reason' => 'Amount does not match'])
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.admin_note', 'Amount does not match');

    $order->refresh();

    expect($order->status)->toBe('failed')
        ->and($order->admin_note)->toBe('Amount does not match');

    $this->assertDatabaseCount('enrollments', 0);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $admin->id,
        'action' => 'order.rejected',
        'auditable_id' => $order->id,
    ]);

    Mail::assertQueued(ReceiptRejectedMail::class, function ($mail) use ($order) {
        return $mail->hasTo($order->user->email) && $mail->reason === 'Amount does not match';
    });
});

it('requires a reason to reject', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->awaitingReview()->create();

    $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/reject", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('rejects rejecting an order that is not awaiting review', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->paid()->create();

    $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/reject", ['reason' => 'nope'])
        ->assertUnprocessable();

    expect($order->fresh()->status)->toBe('paid');
});

it('grants a pending cash order: paid, enrolled, audited', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $order = Order::factory()->create(['method' => 'cash', 'status' => 'pending']);

    $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/grant")
        ->assertOk()
        ->assertJsonPath('data.status', 'paid');

    $this->assertDatabaseHas('enrollments', [
        'user_id' => $order->user_id,
        'course_id' => $order->course_id,
        'order_id' => $order->id,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $admin->id,
        'action' => 'order.granted',
        'auditable_id' => $order->id,
    ]);

    Mail::assertQueued(PaymentConfirmedMail::class, 1);
});

it('refuses to grant an order that is already paid', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $order = Order::factory()->paid()->create();

    $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/grant")
        ->assertUnprocessable();

    Mail::assertNothingQueued();
});

it('streams the private receipt file with its content type', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $order = Order::factory()->create(['method' => 'manual_ccp', 'status' => 'awaiting_review']);

    $path = Storage::disk('local')->putFile(
        "receipts/{$order->id}",
        UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n%%EOF\n"),
    );
    $order->update(['receipt_path' => $path]);

    $response = $this->actingAs($admin)->get("/api/admin/orders/{$order->id}/receipt");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('returns 404 when the order has no receipt', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $order = Order::factory()->create(['method' => 'manual_ccp', 'status' => 'pending', 'receipt_path' => null]);

    $this->actingAs($admin)
        ->getJson("/api/admin/orders/{$order->id}/receipt")
        ->assertNotFound();
});

it('renders the payment mails in both locales', function () {
    $order = Order::factory()->paid()->create();

    expect((new PaymentConfirmedMail($order))->render())->toContain('Construye Academy');

    $order->user->update(['locale' => 'ar']);
    $order->refresh();

    expect((new ReceiptRejectedMail($order, 'سبب الرفض'))->render())->toContain('سبب الرفض');
});
