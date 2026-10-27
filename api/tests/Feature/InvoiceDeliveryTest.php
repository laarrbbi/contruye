<?php

use App\Mail\InvoiceIssuedMail;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * POST /admin/invoices/{invoice}/send had no test at all: its whole body
 * could have been `return response()->noContent()` with the suite green.
 */
it('queues the invoice to the buyer, to nobody else, and records who sent it', function () {
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['email' => 'acheteur@example.es']);
    $order = paidModulesOrder($student);
    completeSellerSettings();

    $invoice = $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
        ->assertCreated()->json('data');

    $this->actingAs($admin)->postJson("/api/admin/invoices/{$invoice['id']}/send")
        ->assertOk()
        ->assertJsonPath('message', 'Invoice sent to acheteur@example.es.');

    // It must reach the buyer — not the admin who pressed the button.
    Mail::assertQueued(InvoiceIssuedMail::class, fn ($mail) => $mail->hasTo('acheteur@example.es'));
    Mail::assertNotQueued(InvoiceIssuedMail::class, fn ($mail) => $mail->hasTo($admin->email));

    $entry = AuditLog::query()->where('action', 'invoice.sent')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->user_id)->toBe($admin->id)
        ->and($entry->meta['number'])->toBe($invoice['number'])
        ->and($entry->meta['to'])->toBe('acheteur@example.es');
});

it('refuses to send an invoice for anyone who is not an admin', function () {
    Mail::fake();

    $student = User::factory()->create();
    // Built directly, not through the API: acting as an admin to create it
    // would authenticate the rest of the test and the guest case below would
    // silently pass as that admin.
    $invoice = Invoice::factory()->create([
        'order_id' => Order::factory()->create(['user_id' => $student->id])->id,
    ]);

    $this->postJson("/api/admin/invoices/{$invoice->id}/send")->assertUnauthorized();
    $this->actingAs($student)->postJson("/api/admin/invoices/{$invoice->id}/send")->assertForbidden();

    Mail::assertNothingQueued();
});

it('throttles repeated sends so a loop cannot flood the buyer', function () {
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $order = paidModulesOrder(User::factory()->create());
    completeSellerSettings();

    $invoice = $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
        ->assertCreated()->json('data');

    // The route carries throttle:10,1.
    foreach (range(1, 10) as $ignored) {
        $this->actingAs($admin)->postJson("/api/admin/invoices/{$invoice['id']}/send")->assertOk();
    }

    $this->actingAs($admin)->postJson("/api/admin/invoices/{$invoice['id']}/send")
        ->assertStatus(429);

    Mail::assertQueuedCount(10);
});

it('reaches the buyer in their own language: subject, sentence and button', function () {
    // The subject, the sentence carrying the invoice number and the button had
    // no French or Arabic translation: every buyer got them in English.
    $admin = User::factory()->create(['role' => 'admin']);
    completeSellerSettings();

    $expected = [
        'fr' => ['Votre facture Construye Academy', 'Veuillez trouver ci-joint votre facture', 'Mes commandes'],
        'ar' => ['فاتورتك من أكاديمية إبني', 'تجد في المرفق فاتورتك', 'طلباتي'],
        'en' => ['Your Construye Academy invoice', 'Please find attached your invoice', 'My orders'],
    ];

    foreach ($expected as $locale => [$subject, $sentence, $button]) {
        $order = paidModulesOrder(User::factory()->create(['locale' => $locale]));
        $invoice = $this->actingAs($admin)
            ->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
            ->assertCreated()->json('data');

        $this->actingAs($admin)->postJson("/api/admin/invoices/{$invoice['id']}/send")->assertOk();

        // Really sent (array transport, sync queue), the way production sends
        // it: in the buyer's language, whatever language the admin works in.
        $message = collect(app('mailer')->getSymfonyTransport()->messages())->last()->getOriginalMessage();

        expect($message->getSubject())->toBe("{$subject} {$invoice['number']}")
            ->and($message->getHtmlBody())->toContain($sentence)
            ->and($message->getHtmlBody())->toContain($invoice['number'])
            ->and($message->getHtmlBody())->toContain($button);
    }
});
