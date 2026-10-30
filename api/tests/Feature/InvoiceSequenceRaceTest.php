<?php

use App\Models\Course;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * B91/B92: the first invoice of a year has no row to lock, so two admins
 * issuing at once can both compute sequence 1 and collide on the unique
 * (year, sequence) index. InvoiceController retries the whole transaction up
 * to three times on SQLSTATE 23000/40001 and rethrows on the fourth.
 *
 * InvoiceTest only walks the happy path, so the retry can be deleted with the
 * suite still green — its absence surfaces only as an admin-facing 500 while
 * a customer is waiting for their invoice. These inject the collision.
 */

/** Minimal but complete seller block, so issuance is not refused for settings. */
function raceSellerSettings(): void
{
    Setting::setValue('invoicing.seller', [
        'business_name' => '[DEMO] Construye Academy',
        'nif' => 'B12345674',
        'address' => 'Calle Mayor 1, 28013 Madrid',
        'phone' => '600 00 00 00',
        'email' => 'contact@example.es',
        'bank_details' => '',
    ]);
}

function racePaidOrder(): Order
{
    return Order::factory()->create([
        'user_id' => User::factory()->create()->id,
        'course_id' => Course::factory()->create(['status' => 'published', 'price_eur' => 490])->id,
        'amount' => 490,
        'currency' => 'EUR',
        'method' => 'manual_transfer',
        'scope' => 'full',
        'status' => 'paid',
        'paid_at' => now(),
    ]);
}

function racePayload(): array
{
    return [
        'buyer_name' => 'Javier Morales',
        'buyer_address' => 'Avenida de la Constitución 12, 41001 Sevilla',
        'iva_rate' => 21,
    ];
}

/**
 * Simulate the rival admin who committed our number between our max(sequence)
 * read and our INSERT: write the very row the pending Invoice is about to
 * write, for a different order, from inside the same transaction.
 */
function stealInvoiceNumber(Invoice $pending, Order $rival): void
{
    DB::table('invoices')->insert([
        'order_id' => $rival->id,
        'number' => $pending->number,
        'year' => $pending->year,
        'sequence' => $pending->sequence,
        'issued_at' => now()->toDateString(),
        'buyer_name' => 'Competidor',
        'buyer_address' => 'Madrid',
        'seller' => json_encode([]),
        'lines' => json_encode([]),
        'iva_rate' => 21,
        'subtotal_net_c' => 0,
        'discount_net_c' => 0,
        'total_net_c' => 0,
        'iva_c' => 0,
        'total_gross_c' => 0,
        'currency' => 'EUR',
        'payment_method_label' => 'Virement CCP',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('absorbs a duplicate invoice number instead of answering 500', function () {
    $admin = User::factory()->admin()->create();
    raceSellerSettings();

    $order = racePaidOrder();
    $rival = racePaidOrder();
    $attempts = 0;

    Invoice::creating(function (Invoice $invoice) use (&$attempts, $rival) {
        // Only the first attempt races; the retry must find the field clear.
        if (++$attempts === 1) {
            stealInvoiceNumber($invoice, $rival);
        }
    });

    $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/invoice", racePayload())
        ->assertCreated()
        ->assertJsonPath('data.number', sprintf('FAC-%d-%05d', now()->year, 1));

    // The rival's row rolled back with the failed attempt, so the sequence is
    // still gap-free — which is the legal requirement the retry protects.
    expect($attempts)->toBe(2)
        ->and(Invoice::query()->count())->toBe(1)
        ->and(Invoice::query()->first()->order_id)->toBe($order->id)
        ->and(Invoice::query()->first()->sequence)->toBe(1);

    // And the abandoned attempt burned no number: the next invoice is 2, not
    // 3. A retry that consumed the sequence would leave a hole an inspector
    // cannot be shown.
    $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$rival->id}/invoice", racePayload())
        ->assertCreated()
        ->assertJsonPath('data.number', sprintf('FAC-%d-%05d', now()->year, 2));

    expect(Invoice::query()->pluck('sequence')->sort()->values()->all())->toBe([1, 2]);
});

it('gives up after three collisions rather than retrying forever', function () {
    $admin = User::factory()->admin()->create();
    raceSellerSettings();

    $order = racePaidOrder();
    $rival = racePaidOrder();
    $attempts = 0;

    // A collision on every single attempt is no longer a race — it is a
    // broken sequence, and looping on it would hang the request thread
    // instead of surfacing the fault.
    Invoice::creating(function (Invoice $invoice) use (&$attempts, $rival) {
        $attempts++;
        stealInvoiceNumber($invoice, $rival);
    });

    $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/invoice", racePayload())
        ->assertStatus(500);

    expect($attempts)->toBe(3)
        ->and(Invoice::query()->count())->toBe(0);
});

it('does not retry a failure that is not a sequence collision', function () {
    $admin = User::factory()->admin()->create();
    raceSellerSettings();

    $order = racePaidOrder();
    $attempts = 0;

    // A bug in the builder must not be replayed three times against the
    // database before it is reported.
    Invoice::creating(function () use (&$attempts) {
        $attempts++;
        throw new RuntimeException('builder exploded');
    });

    $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/invoice", racePayload())
        ->assertStatus(500);

    expect($attempts)->toBe(1)
        ->and(Invoice::query()->count())->toBe(0);
});
