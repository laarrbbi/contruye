<?php

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;

/**
 * C15: invoice numbers are chronological within a fiscal year. Backdating
 * stays possible within 60 days, but never before an invoice already issued.
 * C20: issuing twice for the same order is refused, never a 500.
 */
function issueFor($order, array $extra = [])
{
    return test()->actingAs(User::factory()->admin()->create())
        ->postJson("/api/admin/orders/{$order->id}/invoice", [
            'buyer_name' => 'Javier Morales',
            'buyer_address' => 'Avenida de la Constitución 12, 41001 Sevilla',
            'buyer_nif' => '12345678Z',
            'iva_rate' => 21,
            ...$extra,
        ]);
}

beforeEach(function () {
    completeSellerSettings();
    $this->travelTo(now('Europe/Madrid')->setDate(2026, 10, 20)->setTime(12, 0));
});

it('refuses a date earlier than the last invoice already issued', function () {
    $first = Order::factory()->paid()->create();
    $second = Order::factory()->paid()->create();

    issueFor($first, ['issued_at' => '2026-10-15'])->assertCreated()
        ->assertJsonPath('data.number', 'FAC-2026-00001');

    // Number 2 dated the 10th would precede number 1, dated the 15th.
    issueFor($second, ['issued_at' => '2026-10-10'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('issued_at');

    expect(Invoice::query()->count())->toBe(1);

    // The same day, or later, is fine — and takes the next number, no gap.
    issueFor($second, ['issued_at' => '2026-10-15'])->assertCreated()
        ->assertJsonPath('data.number', 'FAC-2026-00002');
});

it('still lets the first invoice of a year be backdated', function () {
    $order = Order::factory()->paid()->create();

    issueFor($order, ['issued_at' => '2026-09-01'])->assertCreated()
        ->assertJsonPath('data.issued_at', '2026-09-01');
});

it('refuses a second invoice for the same order with a 422, not a 500', function () {
    $order = Order::factory()->paid()->create();

    issueFor($order)->assertCreated();

    issueFor($order)
        ->assertUnprocessable()
        ->assertJsonPath('message', __('An invoice already exists for this order.'));

    expect(Invoice::query()->where('order_id', $order->id)->count())->toBe(1);
});
