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
        ->assertJsonPath('data.rc', null);
});

it('publishes identity and contact, and never the fiscal-only fields', function () {
    Setting::setValue('invoicing.seller', [
        'business_name' => '[DÉMO] Ebni Academy',
        'legal_form' => 'EURL',
        'capital' => '1 000 000 DA',
        'address' => 'Alger',
        'rc' => '16/00-1234567 B 26',
        'nif' => '002616123456789',
        'nis' => '002616123456780',
        'ai' => '16123456789',
        'phone' => '0550 12 34 56',
        'whatsapp' => '0661 22 33 44',
        'email' => 'contact@example.dz',
        'bank_details' => 'CCP 0012345678 clé 90',
        'support_hours' => 'Dim–Jeu, 9h–17h',
    ]);

    $data = $this->getJson('/api/business')->assertOk()->json('data');

    expect($data['rc'])->toBe('16/00-1234567 B 26')
        ->and($data['nif'])->toBe('002616123456789')
        ->and($data['support_hours'])->toBe('Dim–Jeu, 9h–17h')
        // Ready-made international links.
        ->and($data['whatsapp_url'])->toBe('https://wa.me/213661223344')
        ->and($data['phone_tel'])->toBe('tel:+213550123456');

    // The RIB, the NIS and the article d'imposition belong on invoices only.
    expect($data)->not->toHaveKeys(['bank_details', 'nis', 'ai']);
    expect(json_encode($data))->not->toContain('0012345678')
        ->not->toContain('002616123456780');
});

it('turns the ways people actually type a number into one it can dial', function (string $typed, ?string $expected) {
    expect(BusinessProfile::international($typed))->toBe($expected);
})->with([
    'local mobile with spaces' => ['0550 12 34 56', '213550123456'],
    'local with dots' => ['0550.12.34.56', '213550123456'],
    'international with plus' => ['+213 550 12 34 56', '213550123456'],
    'international with 00' => ['00213550123456', '213550123456'],
    'landline' => ['021 23 45 67', '21321234567'],
    'too short to be real' => ['0550', null],
    'not a number at all' => ['appelez-nous', null],
]);

it('refuses a number that cannot be dialled rather than publishing a dead button', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->putJson('/api/admin/invoicing/settings', [
        'whatsapp' => '0550',
    ])->assertUnprocessable()->assertJsonValidationErrors(['whatsapp']);

    $this->actingAs($admin)->putJson('/api/admin/invoicing/settings', [
        'email' => 'pas-un-email',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

    // Leaving it empty is fine — the owner will add it later.
    $this->actingAs($admin)->putJson('/api/admin/invoicing/settings', [
        'whatsapp' => '',
        'rc' => '16/00-1234567 B 26',
    ])->assertOk();
});

it('keeps what the identity was before every change', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Setting::setValue('invoicing.seller', ['rc' => 'ANCIEN-RC']);

    $this->actingAs($admin)->putJson('/api/admin/invoicing/settings', ['rc' => 'NOUVEAU-RC'])->assertOk();

    $entry = AuditLog::query()->where('action', 'invoicing.settings_updated')->latest('id')->first();

    expect($entry->user_id)->toBe($admin->id)
        ->and($entry->meta['before']['rc'])->toBe('ANCIEN-RC')
        ->and($entry->meta['after']['rc'])->toBe('NOUVEAU-RC');
});
