<?php

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\BusinessProfile;

it('shows nothing until the owner has filled it in', function () {
    // Nothing is ever defaulted: an empty field is null, and the site renders
    // nothing for it rather than a placeholder number.
    $this->getJson('/api/business')
        ->assertOk()
        ->assertJsonPath('data.whatsapp', null)
        ->assertJsonPath('data.whatsapp_url', null)
        ->assertJsonPath('data.phone_tel', null)
        ->assertJsonPath('data.registry', null)
        ->assertJsonPath('data.nif', null);
});

it('publishes identity and contact, and never the fiscal-only fields', function () {
    Setting::setValue('invoicing.seller', [
        'business_name' => '[DEMO] Construye Academy',
        'nif' => 'B12345674',
        'address' => 'Calle Mayor 1, 28013 Madrid',
        'legal_form' => 'S.L.',
        'registry' => 'Inscrita en el Registro Mercantil de Madrid, tomo 1, folio 1, hoja M-1',
        'capital' => '3.000 €',
        'phone' => '612 34 56 78',
        'whatsapp' => '661 223 344',
        'email' => 'contact@example.es',
        'bank_details' => 'ES91 2100 0418 4502 0005 1332',
        'support_hours' => 'L–V, 9:00–18:00',
    ]);

    $data = $this->getJson('/api/business')->assertOk()->json('data');

    expect($data['registry'])->toBe('Inscrita en el Registro Mercantil de Madrid, tomo 1, folio 1, hoja M-1')
        ->and($data['nif'])->toBe('B12345674')
        ->and($data['support_hours'])->toBe('L–V, 9:00–18:00')
        // Ready-made international links.
        ->and($data['whatsapp_url'])->toBe('https://wa.me/34661223344')
        ->and($data['phone_tel'])->toBe('tel:+34612345678');

    // The IBAN belongs on invoices only.
    expect($data)->not->toHaveKeys(['bank_details']);
    expect(json_encode($data))->not->toContain('ES91 2100');
});

it('turns the ways people actually type a number into one it can dial', function (string $typed, ?string $expected) {
    expect(BusinessProfile::international($typed))->toBe($expected);
})->with([
    'local mobile with spaces' => ['612 34 56 78', '34612345678'],
    'local with dots' => ['612.34.56.78', '34612345678'],
    'international with plus' => ['+34 612 34 56 78', '34612345678'],
    'international with 00' => ['0034612345678', '34612345678'],
    'landline' => ['912 34 56 78', '34912345678'],
    'another country' => ['+33 6 12 34 56 78', '33612345678'],
    'too short to be real' => ['6123', null],
    'not a number at all' => ['llámanos', null],
]);

it('refuses a number that cannot be dialled rather than publishing a dead button', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->putJson('/api/admin/invoicing/settings', [
        'whatsapp' => '6123',
    ])->assertUnprocessable()->assertJsonValidationErrors(['whatsapp']);

    $this->actingAs($admin)->putJson('/api/admin/invoicing/settings', [
        'email' => 'no-es-un-correo',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

    // Leaving it empty is fine — the owner will add it later.
    $this->actingAs($admin)->putJson('/api/admin/invoicing/settings', [
        'whatsapp' => '',
        'nif' => 'B12345674',
    ])->assertOk();
});

it('keeps what the identity was before every change', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Setting::setValue('invoicing.seller', ['nif' => 'B11111111']);

    $this->actingAs($admin)->putJson('/api/admin/invoicing/settings', ['nif' => 'B22222222'])->assertOk();

    $entry = AuditLog::query()->where('action', 'invoicing.settings_updated')->latest('id')->first();

    expect($entry->user_id)->toBe($admin->id)
        ->and($entry->meta['before']['nif'])->toBe('B11111111')
        ->and($entry->meta['after']['nif'])->toBe('B22222222');
});
