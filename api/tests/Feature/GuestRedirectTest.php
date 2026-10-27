<?php

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;

/**
 * Invoice and receipt links are plain browser navigations. Followed after the
 * session expired, they used to throw "Route [login] not defined" — a 500.
 */
it('sends a signed-out browser to the sign-in page instead of a 500', function () {
    $invoice = Invoice::factory()->create(['order_id' => paidModulesOrder(User::factory()->create())->id]);

    $this->get("/api/admin/invoices/{$invoice->id}/pdf")
        ->assertRedirect(config('app.frontend_url').'/login');

    $order = Order::factory()->create();
    $this->get("/api/orders/{$order->id}/invoice")
        ->assertRedirect(config('app.frontend_url').'/login');
});

it('still answers 401 to an API call', function () {
    $this->getJson('/api/orders')->assertUnauthorized();
});
