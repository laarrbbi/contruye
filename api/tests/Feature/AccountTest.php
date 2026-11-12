<?php

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Support\Account\DeviceSessions;
use App\Support\FiscalId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** A row of the database session store, as a sign-in on that device leaves it. */
function signedInOn(User $user, string $id, string $agent, int $minutesAgo = 0): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '203.0.113.7',
        'user_agent' => $agent,
        'payload' => base64_encode('a:0:{}'),
        'last_activity' => now()->subMinutes($minutesAgo)->getTimestamp(),
    ]);
}

const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
const WINDOWS_EDGE = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0';

it('edits the name, the phone and the invoice details', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/me', [
            'name' => '  Lucía Fernández ',
            'phone' => '+34 612 34 56 78',
            'billing_name' => 'Promociones Fernández SL',
            'billing_nif' => 'b-1234567.4',
            'billing_address' => "Calle Mayor 1\n28013 Madrid",
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Lucía Fernández')
        ->assertJsonPath('data.phone', '+34612345678')
        ->assertJsonPath('data.billing.nif', 'B12345674')
        ->assertJsonPath('data.billing.address', "Calle Mayor 1\n28013 Madrid");

    // Emptied, the details go back to none rather than to an empty string.
    $this->actingAs($user)->patchJson('/api/me', ['billing_name' => ' ', 'billing_nif' => ''])
        ->assertOk()
        ->assertJsonPath('data.billing.name', null)
        ->assertJsonPath('data.billing.nif', null);
});

it('refuses a Spanish fiscal id whose letter does not match', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patchJson('/api/me', ['billing_nif' => '12345678A'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_nif']);
});

it('checks fiscal ids the way the tax agency computes them', function (string $id, bool $valid) {
    expect(FiscalId::isValid($id))->toBe($valid);
})->with([
    'DNI' => ['12345678Z', true],
    'DNI, wrong letter' => ['12345678A', false],
    'NIE X' => ['X1234567L', true],
    'NIE Y' => ['Y1234567X', true],
    'NIE, wrong letter' => ['X1234567A', false],
    'CIF with a digit' => ['B12345674', true],
    'CIF with a letter' => ['Q2826000H', true],
    'CIF, wrong control' => ['B12345675', false],
    'Spanish VAT form' => ['ES12345678Z', true],
    'foreign VAT' => ['DE123456789', true],
    'too short' => ['AB1', false],
]);

it('changes the e-mail only with the current password', function () {
    $user = User::factory()->create(['password' => Hash::make('the-old-password')]);
    User::factory()->create(['email' => 'taken@example.es']);

    $this->actingAs($user)->putJson('/api/me/email', ['email' => 'new@example.es', 'current_password' => 'wrong-password'])
        ->assertUnprocessable()->assertJsonValidationErrors(['current_password']);

    $this->actingAs($user)->putJson('/api/me/email', ['email' => 'taken@example.es', 'current_password' => 'the-old-password'])
        ->assertUnprocessable()->assertJsonValidationErrors(['email']);

    $this->actingAs($user)->putJson('/api/me/email', ['email' => 'new@example.es', 'current_password' => 'the-old-password'])
        ->assertOk()->assertJsonPath('data.email', 'new@example.es');
});

it('changes the password and signs every other device out', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create(['password' => Hash::make('the-old-password')]);
    $someoneElse = User::factory()->create();
    signedInOn($user, str_repeat('a', 40), IPHONE);
    signedInOn($someoneElse, str_repeat('b', 40), IPHONE);

    $this->actingAs($user)->putJson('/api/me/password', [
        'current_password' => 'nope-nope-nope',
        'password' => 'a-brand-new-one',
        'password_confirmation' => 'a-brand-new-one',
    ])->assertUnprocessable()->assertJsonValidationErrors(['current_password']);

    $this->actingAs($user)->putJson('/api/me/password', [
        'current_password' => 'the-old-password',
        'password' => 'a-brand-new-one',
        'password_confirmation' => 'a-brand-new-one',
    ])->assertOk();

    expect(Hash::check('a-brand-new-one', $user->fresh()->password))->toBeTrue();
    // (The test's own requests leave session rows too: the phone is checked by its id.)
    expect(DB::table('sessions')->where('id', str_repeat('a', 40))->exists())->toBeFalse();
    // Nobody else is touched.
    expect(DB::table('sessions')->where('user_id', $someoneElse->id)->count())->toBe(1);
});

it('lists the devices without ever sending a session id out', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    $phone = str_repeat('c', 40);
    signedInOn($user, $phone, IPHONE, 5);
    signedInOn($user, str_repeat('d', 40), WINDOWS_EDGE, 60);

    $response = $this->actingAs($user)->getJson('/api/me/sessions')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.browser', 'Safari')
        ->assertJsonPath('data.0.os', 'iPhone')
        ->assertJsonPath('data.1.browser', 'Edge')
        ->assertJsonPath('data.1.os', 'Windows');

    expect($response->getContent())->not->toContain($phone);

    // One device closed by its public id; the other stays.
    $this->actingAs($user)->deleteJson('/api/me/sessions/'.DeviceSessions::publicId($phone))->assertOk();
    expect(DB::table('sessions')->where('id', $phone)->exists())->toBeFalse();
    expect(DB::table('sessions')->where('id', str_repeat('d', 40))->exists())->toBeTrue();

    // Another user's device cannot be closed from here.
    $other = User::factory()->create();
    signedInOn($other, str_repeat('e', 40), IPHONE);
    $this->actingAs($user)->deleteJson('/api/me/sessions/'.DeviceSessions::publicId(str_repeat('e', 40)))->assertNotFound();
});

it('keeps only the newest sessions, never the current one', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    signedInOn($user, 'current-session-id-000000000000000000000', IPHONE, 300);
    signedInOn($user, str_repeat('f', 40), IPHONE, 1);
    signedInOn($user, str_repeat('g', 40), IPHONE, 10);
    signedInOn($user, str_repeat('h', 40), IPHONE, 100);

    expect(DeviceSessions::keepNewest($user, 2, 'current-session-id-000000000000000000000'))->toBe(2);

    expect(DB::table('sessions')->where('user_id', $user->id)->orderBy('id')->pluck('id')->all())
        ->toBe(['current-session-id-000000000000000000000', str_repeat('f', 40)]);
});

it('exports the student\'s own records as a JSON file', function () {
    $user = User::factory()->create(['name' => 'Javier Morales', 'billing_nif' => '12345678Z']);
    $order = Order::factory()->for($user)->create(['status' => 'paid', 'paid_at' => now()]);
    Invoice::factory()->create(['order_id' => $order->id, 'number' => 'FAC-2026-00042']);
    Order::factory()->create(); // someone else's

    $response = $this->actingAs($user)->get('/api/me/export')->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('attachment; filename="construye-mis-datos-');
    $response->assertJsonPath('account.name', 'Javier Morales')
        ->assertJsonPath('billing.fiscal_id', '12345678Z')
        ->assertJsonCount(1, 'orders')
        ->assertJsonPath('orders.0.invoice', 'FAC-2026-00042');
});

it('shows the owner the invoice details the student asked for', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['billing_name' => 'Promociones Fernández SL', 'billing_nif' => 'B12345674']);
    Order::factory()->for($student)->create(['status' => 'paid', 'paid_at' => now()]);

    $this->actingAs($admin)->getJson('/api/admin/orders')
        ->assertOk()
        ->assertJsonPath('data.0.user.billing.name', 'Promociones Fernández SL')
        ->assertJsonPath('data.0.user.billing.nif', 'B12345674');
});

it('gives the invoice total with each invoiced order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['status' => 'paid', 'paid_at' => now()]);
    Invoice::factory()->create(['order_id' => $order->id, 'total_gross_c' => 49900]);

    $this->actingAs($user)->getJson('/api/orders')
        ->assertOk()
        ->assertJsonPath('data.0.invoice.total_gross_c', 49900);
});

it('closes the device used longest ago when the account signs in on one too many', function () {
    config(['session.driver' => 'database', 'auth.max_devices' => 2]);
    $user = User::factory()->create(['email' => 'javier@example.es', 'password' => Hash::make('password123')]);
    signedInOn($user, str_repeat('i', 40), IPHONE, 5);
    signedInOn($user, str_repeat('j', 40), WINDOWS_EDGE, 600);

    $this->postJson('/api/login', ['email' => 'javier@example.es', 'password' => 'password123'])->assertOk();

    // The phone used five minutes ago stays; the computer idle for ten hours goes.
    expect(DB::table('sessions')->where('id', str_repeat('i', 40))->exists())->toBeTrue();
    expect(DB::table('sessions')->where('id', str_repeat('j', 40))->exists())->toBeFalse();
});
