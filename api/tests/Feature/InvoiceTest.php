<?php

use App\Mail\InvoiceIssuedMail;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Invoice;
use App\Models\Module;
use App\Models\Order;
use App\Models\User;
use App\Services\FrenchNumberSpeller;
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
        'legal_form' => 'EURL',
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
        'amount' => 90000,
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

it('computes the HT/TVA breakdown from TTC amounts, exactly', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create();
    $order = paidModulesOrder($student);
    completeSellerSettings();

    $response = $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
        ->assertCreated();

    $data = $response->json('data');

    // 3 lines at 12 000 TTC → unit HT = round(1 200 000 / 1.19) = 1 008 403 c.
    expect($data['lines'])->toHaveCount(3)
        ->and($data['lines'][0]['unit_net_c'])->toBe(1008403)
        ->and($data['subtotal_net_c'])->toBe(3 * 1008403)
        // Remise TTC 3 600 → HT 302 521 c.
        ->and($data['discount_net_c'])->toBe(302521)
        ->and($data['total_net_c'])->toBe(3 * 1008403 - 302521)
        // HT + TVA must equal the TTC actually paid, to the centime.
        ->and($data['total_net_c'] + $data['iva_c'])->toBe(3240000)
        ->and($data['total_gross_c'])->toBe(3240000)
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

it('bills full-access orders at the agreed price without fabricating a remise', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create();
    $course = Course::factory()->create(['status' => 'published', 'price_dzd' => 90000]);
    completeSellerSettings();

    // A coupon brought the price to 45 000 but discount_percent only stores
    // the (synthetic) volume tier — the pre-discount price is NOT
    // reconstructable, so the invoice must simply bill what was agreed.
    $order = Order::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 45000,
        'discount_percent' => 31,
        'currency' => 'DZD',
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
        ->and($data['lines'][0]['unit_gross_c'])->toBe(4500000)
        ->and($data['discount_net_c'])->toBe(0)
        ->and($data['total_net_c'] + $data['iva_c'])->toBe(4500000);
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
    // than fabricate an "accès complet" line.
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
            'buyer_name' => 'SARL Promo-Bat',
            'buyer_rc' => '16/00-7654321 B 26',
            'buyer_nif' => '002616987654321',
            'iva_rate' => 0,
        ]))
        ->assertOk()
        ->json('data');

    expect($updated['number'])->toBe($created['number'])
        ->and($updated['issued_at'])->toBe($created['issued_at'])
        ->and($updated['buyer_name'])->toBe('SARL Promo-Bat')
        // 0 % TVA → HT equals TTC.
        ->and($updated['iva_c'])->toBe(0)
        ->and($updated['total_net_c'])->toBe(3240000);
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

it('spells French amounts correctly, including the awkward ones', function () {
    $cases = [
        [7100, 'soixante et onze dinars algériens'],
        [8000, 'quatre-vingts dinars algériens'],
        [8100, 'quatre-vingt-un dinars algériens'],
        [9100, 'quatre-vingt-onze dinars algériens'],
        [10000, 'cent dinars algériens'],
        [20000, 'deux cents dinars algériens'],
        [20100, 'deux cent un dinars algériens'],
        [100000, 'mille dinars algériens'],
        [198000, 'mille neuf cent quatre-vingts dinars algériens'],
        [8000000, 'quatre-vingt mille dinars algériens'],
        [9000000, 'quatre-vingt-dix mille dinars algériens'],
        [3240000, 'trente-deux mille quatre cents dinars algériens'],
        [20000000, 'deux cent mille dinars algériens'],
        [100000000, 'un million de dinars algériens'],
        // million/milliard are nouns: the plural of what precedes them stays.
        [8000000000, 'quatre-vingts millions de dinars algériens'],
        [20000000000, 'deux cents millions de dinars algériens'],
        [23050000000, 'deux cent trente millions cinq cent mille dinars algériens'],
        [100, 'un dinar algérien'],
        [150, 'un dinar algérien et cinquante centimes'],
        [1008403, 'dix mille quatre-vingt-quatre dinars algériens et trois centimes'],
    ];

    foreach ($cases as [$centimes, $expected]) {
        expect(FrenchNumberSpeller::amountInWords($centimes, 'DZD'))->toBe($expected);
    }
});

it('dates an invoice by the Algiers calendar, not UTC', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = paidModulesOrder(User::factory()->create());
    completeSellerSettings();

    // 31 December 23:30 UTC is already 1 January in Algiers (UTC+1): the
    // facture must carry the new year's date and start that year's sequence.
    $this->travelTo(CarbonImmutable::parse('2026-12-31 23:30:00', 'UTC'));

    $data = $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/invoice", [
            'buyer_name' => 'Karim Benali',
            'buyer_address' => 'Alger',
            'iva_rate' => 19,
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

    // The émetteur block is COPIED onto the document, not referenced.
    $invoice = Invoice::query()->findOrFail($created['id']);

    expect($invoice->seller['business_name'])->toBe('[DEMO] Construye Academy')
        ->and($invoice->seller['rc'])->toBe('16/00-1234567 B 26')
        ->and($invoice->seller['nif'])->toBe('002616123456789');

    // The company re-registers and moves. Correcting the settings must never
    // rewrite a numbered document that has already been handed to a buyer.
    $this->actingAs($admin)->putJson('/api/admin/invoicing/settings', [
        'business_name' => 'Construye Academy SPA',
        'legal_form' => 'SPA',
        'capital' => '5 000 000 DA',
        'address' => 'Nouvelle adresse, Oran',
        'rc' => '31/00-9999999 B 26',
        'nif' => '003116999999999',
        'nis' => '003116999999990',
        'ai' => '31999999999',
    ])->assertOk()->assertJsonPath('seller.rc', '31/00-9999999 B 26');

    expect($invoice->fresh()->seller['rc'])->toBe('16/00-1234567 B 26')
        ->and($invoice->fresh()->seller['business_name'])->toBe('[DEMO] Construye Academy');

    // And what is printed reads from that snapshot, not from the live settings.
    $html = view('pdf.invoice', [
        'invoice' => $invoice->fresh()->load(['order.user', 'order.course']),
        'amountInWords' => FrenchNumberSpeller::amountInWords($invoice->total_gross_c, $invoice->currency),
    ])->render();

    expect($html)->toContain('16/00-1234567 B 26')
        ->and($html)->toContain('[DEMO] Construye Academy')
        ->and($html)->not->toContain('31/00-9999999 B 26')
        ->and($html)->not->toContain('Construye Academy SPA');

    // A correction on the invoice must not quietly re-pull the new block either.
    $this->actingAs($admin)
        ->putJson("/api/admin/invoices/{$invoice->id}", invoicePayload(['buyer_name' => 'Corrigé']))
        ->assertOk();

    expect($invoice->fresh()->seller['rc'])->toBe('16/00-1234567 B 26');

    // The NEXT facture carries the new identity: the snapshot is taken per
    // document at issuance, it is not a global freeze on the settings.
    $next = paidModulesOrder(User::factory()->create());
    $nextId = $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$next->id}/invoice", invoicePayload())
        ->assertCreated()
        ->json('data.id');

    expect(Invoice::query()->findOrFail($nextId)->seller['rc'])->toBe('31/00-9999999 B 26');
});

it('accepts a backdated issue date and takes the sequence from that fiscal year', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    completeSellerSettings();

    // Mid-January: December's factures still have to be issued, dated
    // December, and numbered in the year that is now closed.
    $this->travelTo(CarbonImmutable::parse('2027-01-15 09:00:00', 'Africa/Algiers'));

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

it('re-sends a facture for admins only, not even for the buyer it belongs to', function () {
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

it('emails the facture to the buyer, audits it, and throttles the endpoint', function () {
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
    // admin UI) cannot mailbomb a buyer with re-issued factures.
    foreach (range(2, 10) as $ignored) {
        $this->actingAs($admin)->postJson("/api/admin/invoices/{$invoiceId}/send")->assertOk();
    }

    $this->actingAs($admin)->postJson("/api/admin/invoices/{$invoiceId}/send")->assertStatus(429);

    Mail::assertQueued(InvoiceIssuedMail::class, 10);
});

it('audits the issuance and the correction of a numbered facture', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = paidModulesOrder(User::factory()->create());
    completeSellerSettings();

    $created = $this->actingAs($admin)
        ->postJson("/api/admin/orders/{$order->id}/invoice", invoicePayload())
        ->assertCreated()
        ->json('data');

    $this->actingAs($admin)
        ->putJson("/api/admin/invoices/{$created['id']}", invoicePayload([
            'buyer_name' => 'SARL Promo-Bat',
            'iva_rate' => 9,
        ]))
        ->assertOk();

    $issued = AuditLog::query()->where('action', 'invoice.created')->latest('id')->first();
    $corrected = AuditLog::query()->where('action', 'invoice.updated')->latest('id')->first();

    expect($issued)->not->toBeNull()
        ->and($issued->user_id)->toBe($admin->id)
        ->and($issued->auditable_id)->toBe($created['id'])
        ->and($issued->meta['number'])->toBe($created['number']);

    // A correction to a numbered document has to keep what it overwrote, or
    // the original facture becomes unrecoverable.
    expect($corrected)->not->toBeNull()
        ->and($corrected->meta['before']['buyer_name'])->toBe('Karim Benali')
        ->and($corrected->meta['after']['buyer_name'])->toBe('SARL Promo-Bat')
        ->and((float) $corrected->meta['before']['iva_rate'])->toBe(19.0)
        ->and((float) $corrected->meta['after']['iva_rate'])->toBe(9.0)
        ->and($corrected->meta['before']['iva_c'])->not->toBe($corrected->meta['after']['iva_c']);
});
