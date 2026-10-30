<?php

use App\Mail\InvoiceIssuedMail;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Invoice;
use App\Models\Module;
use App\Models\Order;
use App\Models\User;
use App\Services\SpanishNumberSpeller;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

it('blocks invoicing endpoints for guests and non-admins', function () {
    $this->getJson('/api/admin/invoicing/settings')->assertUnauthorized();

    $student = User::factory()->create();
    $order = paidModulesOrder($student);

    $this->actingAs($student)->getJson('/api/admin/invoicing/settings')->assertForbidden();
    $this->actingAs($student)->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
        ->assertForbidden();
});

it('round-trips seller settings and reports missing required fields', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->getJson('/api/admin/invoicing/settings')
        ->assertOk()
        ->assertJsonPath('complete', false)
        ->assertJsonPath('missing', Invoice::REQUIRED_SELLER_FIELDS);

    $this->actingAs($admin)->putJson('/api/admin/invoicing/settings', [
        'business_name' => '[DEMO] Construye Academy',
        'legal_form' => 'S.L.',
    ])->assertOk()->assertJsonPath('complete', false);

    completeSellerSettings();

    $this->actingAs($admin)->getJson('/api/admin/invoicing/settings')
        ->assertOk()
        ->assertJsonPath('complete', true)
        ->assertJsonPath('missing', [])
        ->assertJsonPath('seller.business_name', '[DEMO] Construye Academy');
});

it('refuses to invoice unpaid orders, incomplete settings, and duplicates', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create();
    $order = paidModulesOrder($student);

    // Seller settings incomplete → blocked with the missing list.
    $this->actingAs($admin)->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
        ->assertUnprocessable()
        ->assertJsonStructure(['missing']);

    completeSellerSettings();

    // Unpaid order → blocked.
    $pending = Order::factory()->create([
        'user_id' => $student->id,
        'course_id' => $order->course_id,
        'status' => 'pending',
        'amount' => 490,
        'scope' => 'full',
        'method' => 'cash',
    ]);
    $this->actingAs($admin)->postJson("/api/admin/orders/{$pending->id}/invoice", invoicePayload())
        ->assertUnprocessable();

    // First issuance passes, second is refused.
    $this->actingAs($admin)->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
        ->assertCreated();
    $this->actingAs($admin)->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
        ->assertUnprocessable();
});

it('computes the net/IVA breakdown from IVA-inclusive amounts, exactly', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create();
    $order = paidModulesOrder($student);
    completeSellerSettings();

    $response = $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
        ->assertCreated();

    $data = $response->json('data');

    // 3 lines at 49 € IVA included → unit net = round(4 900 / 1.21) = 4 050 c.
    expect($data['lines'])->toHaveCount(3)
        ->and($data['lines'][0]['unit_net_c'])->toBe(4050)
        ->and($data['subtotal_net_c'])->toBe(3 * 4050)
        // Discount 15 € IVA included → 12,40 € net.
        ->and($data['discount_net_c'])->toBe(1240)
        ->and($data['total_net_c'])->toBe(3 * 4050 - 1240)
        // Net + IVA must equal what was actually paid, to the cent.
        ->and($data['total_net_c'] + $data['iva_c'])->toBe(13200)
        ->and($data['iva_c'])->toBe(2290)
        ->and($data['total_gross_c'])->toBe(13200)
        ->and($data['number'])->toBe('FAC-'.now()->year.'-00001');
});

it('numbers invoices sequentially within a year', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    completeSellerSettings();

    foreach ([1, 2, 3] as $expected) {
        $student = User::factory()->create();
        $order = paidModulesOrder($student);

        $this->actingAs($admin)
            ->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
            ->assertCreated()
            ->assertJsonPath('data.number', sprintf('FAC-%d-%05d', now()->year, $expected));
    }
});

it('bills full-access orders at the agreed price without fabricating a discount', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create();
    $course = Course::factory()->create(['status' => 'published', 'price_eur' => 490]);
    completeSellerSettings();

    // A coupon brought the price to 245 but discount_percent only stores
    // the (synthetic) volume tier — the pre-discount price is NOT
    // reconstructable, so the invoice must simply bill what was agreed.
    $order = Order::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 245,
        'discount_percent' => 31,
        'currency' => 'EUR',
        'method' => 'cash',
        'scope' => 'full',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $data = $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
        ->assertCreated()
        ->json('data');

    expect($data['lines'])->toHaveCount(1)
        ->and($data['lines'][0]['unit_gross_c'])->toBe(24500)
        ->and($data['discount_net_c'])->toBe(0)
        ->and($data['total_net_c'] + $data['iva_c'])->toBe(24500);
});

it('corrects from the stored snapshot even when the order lines are gone', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create();
    $order = paidModulesOrder($student);
    completeSellerSettings();

    $created = $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
        ->assertCreated()
        ->json('data');

    // Deleting a sold module is now refused outright — the order lines an
    // invoice is built from cannot be taken away behind its back.
    $soldModule = $order->orderModules()->first()->module_id;
    $this->actingAs($admin)->deleteJson("/api/admin/modules/{$soldModule}")
        ->assertUnprocessable();
    expect($order->orderModules()->count())->toBe(3);

    // Even so, a correction must never rebuild from live data: force the
    // lines away directly and check the snapshot still stands on its own.
    $order->orderModules()->delete();

    $updated = $this->actingAs($admin)
        ->putJson("/api/admin/invoices/{$created['id']}", invoicePayload(['buyer_name' => 'Corrigé']))
        ->assertOk()
        ->json('data');

    // The legal snapshot survives: same three designations, same totals.
    expect($updated['lines'])->toHaveCount(3)
        ->and(array_column($updated['lines'], 'designation'))
        ->toBe(array_column($created['lines'], 'designation'))
        ->and($updated['total_net_c'])->toBe($created['total_net_c'])
        ->and($updated['iva_c'])->toBe($created['iva_c']);

    // And a fresh order in the same broken state refuses to invoice rather
    // than fabricate an "acceso completo" line.
    $other = paidModulesOrder(User::factory()->create());
    $other->orderModules()->delete();
    $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$other->id}/invoice", invoicePayload())
        ->assertUnprocessable();
});

it('rejects out-of-range issue dates', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create();
    $order = paidModulesOrder($student);
    completeSellerSettings();

    foreach ([now()->addDay(), now()->subDays(90)] as $bad) {
        $this->actingAs($admin)
            ->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload([
                'issued_at' => $bad->toDateString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('issued_at');
    }
});

it('lets the admin correct an invoice without touching number or date', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create();
    $order = paidModulesOrder($student);
    completeSellerSettings();

    $created = $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
        ->assertCreated()
        ->json('data');

    $updated = $this->actingAs($admin)
        ->putJson("/api/admin/invoices/{$created['id']}", invoicePayload([
            'buyer_name' => 'Promociones Ejemplo S.L.',
            'buyer_nif' => 'B87654321',
            'iva_rate' => 0,
        ]))
        ->assertOk()
        ->json('data');

    expect($updated['number'])->toBe($created['number'])
        ->and($updated['issued_at'])->toBe($created['issued_at'])
        ->and($updated['buyer_name'])->toBe('Promociones Ejemplo S.L.')
        ->and($updated['buyer_nif'])->toBe('B87654321')
        // 0 % IVA → net equals what was paid.
        ->and($updated['iva_c'])->toBe(0)
        ->and($updated['total_net_c'])->toBe(13200);
});

it('serves the PDF to the buyer and the admin, and to nobody else', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create();
    $other = User::factory()->create();
    $order = paidModulesOrder($student);
    completeSellerSettings();

    // No invoice yet → 404 for the owner.
    $this->actingAs($student)->get("/api/orders/{$order->id}/invoice")->assertNotFound();

    $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
        ->assertCreated();

    $this->actingAs($other)->get("/api/orders/{$order->id}/invoice")->assertNotFound();

    $this->actingAs($student)->get("/api/orders/{$order->id}/invoice")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $invoiceId = Invoice::query()->firstOrFail()->id;
    $this->actingAs($admin)->get("/api/admin/invoices/{$invoiceId}/pdf")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('exposes the invoice on both order resources once issued', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create();
    $order = paidModulesOrder($student);
    completeSellerSettings();

    $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
        ->assertCreated();

    $this->actingAs($student)->getJson('/api/orders')
        ->assertOk()
        ->assertJsonPath('data.0.invoice.number', 'FAC-'.now()->year.'-00001');

    $this->actingAs($admin)->getJson("/api/admin/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.invoice.number', 'FAC-'.now()->year.'-00001');
});

it('spells Spanish amounts correctly, including the awkward ones', function () {
    $cases = [
        [100, 'un euro'],
        [2100, 'veintiún euros'],
        [2200, 'veintidós euros'],
        [1600, 'dieciséis euros'],
        [3100, 'treinta y un euros'],
        [4900, 'cuarenta y nueve euros'],
        [10000, 'cien euros'],
        [10100, 'ciento un euros'],
        [50000, 'quinientos euros'],
        [70000, 'setecientos euros'],
        [90000, 'novecientos euros'],
        [100000, 'mil euros'],
        [149000, 'mil cuatrocientos noventa euros'],
        [2100000, 'veintiún mil euros'],
        [20100000, 'doscientos un mil euros'],
        [13200, 'ciento treinta y dos euros'],
        [100000000, 'un millón de euros'],
        [200000000, 'dos millones de euros'],
        [125000000, 'un millón doscientos cincuenta mil euros'],
        // "mil millones", never "un billón" (a million millions).
        [100000000000, 'mil millones de euros'],
        [150, 'un euro con cincuenta céntimos'],
        [2101, 'veintiún euros con un céntimo'],
        [4050, 'cuarenta euros con cincuenta céntimos'],
        [0, 'cero euros'],
    ];

    foreach ($cases as [$centimos, $expected]) {
        expect(SpanishNumberSpeller::amountInWords($centimos, 'EUR'))->toBe($expected);
    }
});

it('dates an invoice by the Madrid calendar, not UTC', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = paidModulesOrder(User::factory()->create());
    completeSellerSettings();

    // 31 December 23:30 UTC is already 1 January in Madrid (UTC+1 in winter):
    // the factura must carry the new year's date and start that year's
    // sequence.
    $this->travelTo(CarbonImmutable::parse('2026-12-31 23:30:00', 'UTC'));

    $data = $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/invoice", [
            'buyer_name' => 'Javier Morales',
            'buyer_address' => 'Madrid',
            'iva_rate' => 21,
        ])
        ->assertCreated()
        ->json('data');

    expect($data['issued_at'])->toStartWith('2027-01-01')
        ->and($data['number'])->toBe('FAC-2027-00001');

    $this->travelBack();
});

it('freezes the seller block into the invoice at issuance', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = paidModulesOrder(User::factory()->create());
    completeSellerSettings();

    $created = $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
        ->assertCreated()
        ->json('data');

    // The emisor block is COPIED onto the document, not referenced.
    $invoice = Invoice::query()->findOrFail($created['id']);

    expect($invoice->seller['business_name'])->toBe('[DEMO] Construye Academy')
        ->and($invoice->seller['registry'])->toBe('Inscrita en el Registro Mercantil de Madrid, tomo 1, folio 1, hoja M-1')
        ->and($invoice->seller['nif'])->toBe('B12345674');

    // The company changes form and moves. Correcting the settings must never
    // rewrite a numbered document that has already been handed to a buyer.
    $this->actingAs($admin)->putJson('/api/admin/invoicing/settings', [
        'business_name' => 'Construye Academy S.A.',
        'legal_form' => 'S.A.',
        'capital' => '60.000 €',
        'address' => 'Nueva dirección, Valencia',
        'registry' => 'Inscrita en el Registro Mercantil de Valencia, tomo 9, folio 9, hoja V-9',
        'nif' => 'A99999999',
    ])->assertOk()->assertJsonPath('seller.nif', 'A99999999');

    expect($invoice->fresh()->seller['nif'])->toBe('B12345674')
        ->and($invoice->fresh()->seller['business_name'])->toBe('[DEMO] Construye Academy');

    // And what is printed reads from that snapshot, not from the live settings.
    $html = view('pdf.invoice', [
        'invoice' => $invoice->fresh()->load(['order.user', 'order.course']),
        'amountInWords' => SpanishNumberSpeller::amountInWords($invoice->total_gross_c, $invoice->currency),
    ])->render();

    expect($html)->toContain('B12345674')
        ->and($html)->toContain('[DEMO] Construye Academy')
        ->and($html)->toContain('hoja M-1')
        ->and($html)->toContain('ciento treinta y dos euros')
        ->and($html)->not->toContain('A99999999')
        ->and($html)->not->toContain('Construye Academy S.A.');

    // A correction on the invoice must not quietly re-pull the new block either.
    $this->actingAs($admin)
        ->putJson("/api/admin/invoices/{$invoice->id}", invoicePayload(['buyer_name' => 'Corrigé']))
        ->assertOk();

    expect($invoice->fresh()->seller['nif'])->toBe('B12345674');

    // The NEXT factura carries the new identity: the snapshot is taken per
    // document at issuance, it is not a global freeze on the settings.
    $next = paidModulesOrder(User::factory()->create());
    $nextId = $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$next->id}/invoice", invoicePayload())
        ->assertCreated()
        ->json('data.id');

    expect(Invoice::query()->findOrFail($nextId)->seller['nif'])->toBe('A99999999');
});

it('accepts a backdated issue date and takes the sequence from that fiscal year', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    completeSellerSettings();

    // Mid-January: December's facturas still have to be issued, dated
    // December, and numbered in the year that is now closed.
    $this->travelTo(CarbonImmutable::parse('2027-01-15 09:00:00', 'Europe/Madrid'));

    $january = paidModulesOrder(User::factory()->create());
    $this->actingAs($admin)->postJson("/api/admin/orders/{$january->id}/invoice", invoicePayload())
        ->assertCreated()
        ->assertJsonPath('data.issued_at', '2027-01-15')
        ->assertJsonPath('data.number', 'FAC-2027-00001');

    // The 60-day window is inclusive at its edge (16 November, in 2026's run)...
    $edge = paidModulesOrder(User::factory()->create());
    $this->actingAs($admin)->postJson("/api/admin/orders/{$edge->id}/invoice", invoicePayload([
        'issued_at' => now()->subDays(60)->toDateString(),
    ]))
        ->assertCreated()
        ->assertJsonPath('data.issued_at', now()->subDays(60)->toDateString())
        ->assertJsonPath('data.number', 'FAC-2026-00001');

    $december = paidModulesOrder(User::factory()->create());
    $this->actingAs($admin)->postJson("/api/admin/orders/{$december->id}/invoice", invoicePayload([
        'issued_at' => '2026-12-20',
    ]))
        ->assertCreated()
        // Kept verbatim — not quietly replaced by today...
        ->assertJsonPath('data.issued_at', '2026-12-20')
        // ...and numbered in 2026's run, not punched as a hole in 2027's.
        ->assertJsonPath('data.number', 'FAC-2026-00002');

    // 2027 carries on where it was: the two years are independent sequences.
    $next = paidModulesOrder(User::factory()->create());
    $this->actingAs($admin)->postJson("/api/admin/orders/{$next->id}/invoice", invoicePayload())
        ->assertCreated()
        ->assertJsonPath('data.number', 'FAC-2027-00002');

    // Backdating never reaches before an invoice already issued in that run:
    // FAC-2026-00003 dated 1 December would precede FAC-2026-00002 (C15).
    $outOfOrder = paidModulesOrder(User::factory()->create());
    $this->actingAs($admin)->postJson("/api/admin/orders/{$outOfOrder->id}/invoice", invoicePayload([
        'issued_at' => '2026-12-01',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('issued_at');

    // ...and closed one day past it.
    $tooFar = paidModulesOrder(User::factory()->create());
    $this->actingAs($admin)->postJson("/api/admin/orders/{$tooFar->id}/invoice", invoicePayload([
        'issued_at' => now()->subDays(61)->toDateString(),
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('issued_at');

    $this->travelBack();
});

it('re-sends an invoice for admins only, not even for the buyer it belongs to', function () {
    Mail::fake();

    $student = User::factory()->create();
    $invoice = Invoice::factory()->create(['order_id' => paidModulesOrder($student)->id]);

    // Guest first — `actingAs` sticks for the rest of the test.
    $this->postJson("/api/admin/invoices/{$invoice->id}/send")->assertUnauthorized();

    // The buyer owns the document but not the act of issuing it again.
    $this->actingAs($student)->postJson("/api/admin/invoices/{$invoice->id}/send")->assertForbidden();

    Mail::assertNothingQueued();
    expect(AuditLog::query()->where('action', 'invoice.sent')->count())->toBe(0);
});

it('emails the invoice to the buyer, audits it, and throttles the endpoint', function () {
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create();
    $order = paidModulesOrder($student);
    completeSellerSettings();

    $invoiceId = $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin)->postJson("/api/admin/invoices/{$invoiceId}/send")
        ->assertOk()
        ->assertJsonPath('message', "Invoice sent to {$student->email}.");

    // Queued (the PDF is rendered by the worker) and addressed to the BUYER —
    // never to the admin who pressed the button.
    Mail::assertQueued(InvoiceIssuedMail::class, 1);
    Mail::assertQueued(
        InvoiceIssuedMail::class,
        fn (InvoiceIssuedMail $mail) => $mail->hasTo($student->email)
            && ! $mail->hasTo($admin->email)
            && $mail->invoice->id === $invoiceId,
    );

    $entry = AuditLog::query()->where('action', 'invoice.sent')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->user_id)->toBe($admin->id)
        ->and($entry->auditable_id)->toBe($invoiceId)
        ->and($entry->meta['to'])->toBe($student->email);

    // Ten a minute: the eleventh call is refused, so a stuck admin (or a stuck
    // admin UI) cannot mailbomb a buyer with re-issued invoices.
    foreach (range(2, 10) as $ignored) {
        $this->actingAs($admin)->postJson("/api/admin/invoices/{$invoiceId}/send")->assertOk();
    }

    $this->actingAs($admin)->postJson("/api/admin/invoices/{$invoiceId}/send")->assertStatus(429);

    Mail::assertQueued(InvoiceIssuedMail::class, 10);
});

it('audits the issuance and the correction of a numbered invoice', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = paidModulesOrder(User::factory()->create());
    completeSellerSettings();

    $created = $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
        ->assertCreated()
        ->json('data');

    $this->actingAs($admin)
        ->putJson("/api/admin/invoices/{$created['id']}", invoicePayload([
            'buyer_name' => 'Promociones Alameda S.L.',
            'iva_rate' => 10,
        ]))
        ->assertOk();

    $issued = AuditLog::query()->where('action', 'invoice.created')->latest('id')->first();
    $corrected = AuditLog::query()->where('action', 'invoice.updated')->latest('id')->first();

    expect($issued)->not->toBeNull()
        ->and($issued->user_id)->toBe($admin->id)
        ->and($issued->auditable_id)->toBe($created['id'])
        ->and($issued->meta['number'])->toBe($created['number']);

    // A correction to a numbered document has to keep what it overwrote, or
    // the original invoice becomes unrecoverable.
    expect($corrected)->not->toBeNull()
        ->and($corrected->meta['before']['buyer_name'])->toBe('Javier Morales')
        ->and($corrected->meta['after']['buyer_name'])->toBe('Promociones Alameda S.L.')
        ->and((float) $corrected->meta['before']['iva_rate'])->toBe(21.0)
        ->and((float) $corrected->meta['after']['iva_rate'])->toBe(10.0)
        ->and($corrected->meta['before']['iva_c'])->not->toBe($corrected->meta['after']['iva_c']);
});
