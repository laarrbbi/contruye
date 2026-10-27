<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('registers a new student and logs them in', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Yacine Benali',
        'email' => 'yacine@example.es',
        'phone' => '0550 12 34 56',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'locale' => 'fr',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'yacine@example.es')
        ->assertJsonPath('data.role', 'student')
        ->assertJsonPath('data.phone', '0550123456');

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'yacine@example.es', 'role' => 'student']);
});

it('rejects registration with an invalid algerian phone number', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Test',
        'email' => 'test@example.es',
        'phone' => '0450123456',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['phone']);
});

it('registers a buyer who lives abroad with an international number', function (string $typed, string $stored) {
    // The sales page invites buyers in France and elsewhere; the rule took an
    // Algerian mobile only, so they could not open an account to pay with.
    $this->postJson('/api/register', [
        'name' => 'Karim Haddad',
        'email' => 'karim@example.fr',
        'phone' => $typed,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])
        ->assertCreated()
        ->assertJsonPath('data.phone', $stored);

    $this->assertDatabaseHas('users', ['email' => 'karim@example.fr', 'phone' => $stored]);
})->with([
    'France, with spaces' => ['+33 6 12 34 56 78', '+33612345678'],
    '00 typed for +' => ['0033 6 12 34 56 78', '+33612345678'],
    // Kept, the bracketed zero would make +330612345678, which rings nowhere.
    'French (0) notation' => ['+33 (0)6 12 34 56 78', '+33612345678'],
    'Canada, brackets and dash' => ['+1 (514) 555-0123', '+15145550123'],
    'dots' => ['0550.12.34.56', '0550123456'],
    'Algerian mobile written internationally' => ['+213 5 50 12 34 56', '+213550123456'],
    // Only Algeria's own code is held to the mobile rule, not every +2….
    'Morocco, another code starting with 2' => ['+212 6 12 34 56 78', '+212612345678'],
    // "00" is read as "+" at the start only, never inside the number.
    'zeros inside the number' => ['+33 6 00 11 22 33', '+33600112233'],
    // How WhatsApp hands over a number copied in Arabic: LRE … PDF around it.
    'copied from WhatsApp in Arabic' => ["\u{202A}+213 550 12 34 56\u{202C}", '+213550123456'],
    'no-break spaces from a French page' => ["0550\u{00A0}12\u{00A0}34\u{00A0}56", '0550123456'],
    // The marks below sit inside the number on purpose: at either end, the
    // TrimStrings middleware removes most of them before normalisePhone()
    // runs, and the case would pass with the separator list emptied.
    'tabs, pasted from a spreadsheet' => ["0550\t12\t34\t56", '0550123456'],
    'direction marks between the groups' => ["0550\u{200E}12\u{200F}34\u{200E}56", '0550123456'],
    'wrapped in isolates, thin spaces' => ["\u{2068}+33\u{2009}6\u{2009}12 34 56 78\u{2069}", '+33612345678'],
    'stray BOM, Arabic letter mark and rarer spaces' => ["+33\u{FEFF}6\u{061C}12\u{3000}34\u{205F}56\u{1680}78", '+33612345678'],
    'shortest: eight digits' => ['+677 12345', '+67712345'],
    'longest: fifteen digits' => ['+86 123 4567 8901 23', '+861234567890123'],
]);

it('refuses a phone number that is neither an Algerian mobile nor international', function (string $typed) {
    $this->postJson('/api/register', [
        'name' => 'Test',
        'email' => 'test@example.es',
        'phone' => $typed,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])
        ->assertUnprocessable()
        // The message says what is accepted, so a buyer abroad knows to add
        // the country code instead of guessing.
        ->assertJsonValidationErrors(['phone' => __('Invalid number: Algerian mobile (05, 06, 07) or international number starting with +')]);

    $this->assertDatabaseMissing('users', ['email' => 'test@example.es']);
})->with([
    'too short' => ['+12'],
    'seven digits' => ['+677 1234'],
    'sixteen digits' => ['+86 123 4567 8901 234'],
    'country code starting with 0' => ['+0550123456'],
    'international digits without + or 00' => ['33612345678'],
    'letters' => ['+33 6 12 AB 56 78'],
    'two plus signs' => ['++33612345678'],
    'Algerian landline written internationally' => ['+213 21 12 34 56'],
    // Refused as 0450123456 is: written internationally, it is still no mobile.
    'Algerian non-mobile written internationally' => ['+213 4 50 12 34 56'],
    // The home zero kept after the code: a number nobody can dial.
    'Algerian mobile with its home zero after +213' => ['+213 0550 12 34 56'],
    'French mobile with its home zero after +33' => ['+33 06 12 34 56 78'],
    'the same, with 00 for +' => ['0033 06 12 34 56 78'],
]);

it('logs in with valid credentials', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk()->assertJsonPath('data.id', $user->id);
    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable();
    $this->assertGuest();
});

it('returns the authenticated user on /api/me', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

it('blocks /api/me for guests', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});

it('logs out the authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/logout')->assertOk();

    $this->assertGuest('web');
});

it('forces the student role however the client fills the registration form', function () {
    // The role is not a validated field, so submitting it must be inert —
    // otherwise /register is a self-service admin factory.
    $this->postJson('/api/register', [
        'name' => 'Faux Admin',
        'email' => 'faux@example.es',
        'phone' => '0551234567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'admin',
    ])
        ->assertCreated()
        ->assertJsonPath('data.role', 'student');

    expect(User::firstWhere('email', 'faux@example.es')->isAdmin())->toBeFalse();

    $this->assertDatabaseMissing('users', ['role' => 'admin']);
});

it('answers a wrong password and an unknown address with the very same 422', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $wrongPassword = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'not-the-password',
    ])->assertUnprocessable();

    $unknownEmail = $this->postJson('/api/login', [
        'email' => 'ghost@example.es',
        'password' => 'not-the-password',
    ])->assertUnprocessable();

    // Byte-identical: an `exists:users,email` rule or an early return would
    // turn the login form into an account oracle — "does this person have an
    // account here" answered to anyone who asks.
    expect($unknownEmail->json())->toBe($wrongPassword->json())
        ->and($wrongPassword->json('errors'))->toHaveKey('email')
        ->and($wrongPassword->json('message'))->not->toBeEmpty();

    $this->assertGuest();
});

it('throttles the auth routes at ten attempts a minute per IP', function () {
    $user = User::factory()->create(['password' => 'password123']);

    // Credential stuffing is what this limiter exists for, so the counter must
    // move on FAILED attempts, and it is shared by every auth route.
    foreach (range(1, 10) as $ignored) {
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable();
    }

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertStatus(429);

    // …including the correct password: the eleventh request never reaches the
    // controller, so a locked-out attacker cannot fall through to a success.
    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123'])
        ->assertStatus(429);

    $this->assertGuest();

    // One bucket for all four routes — registering is not a way around it.
    $this->postJson('/api/forgot-password', ['email' => $user->email])->assertStatus(429);
    $this->postJson('/api/register', [
        'name' => 'Trop Tard',
        'email' => 'trop.tard@example.es',
        'phone' => '0551234567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(429);
});

it('ends the session on logout: guard dropped, session flushed, CSRF token rotated', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123'])->assertOk();

    // Carried into the logout request so the flush has something to prove it
    // happened — the session id itself is no witness here, because the test
    // client sends no session cookie and every request starts a fresh id.
    $this->withSession(['_token' => 'csrf-token-before-logout', 'checkout_draft' => 'order-42']);

    $response = $this->postJson('/api/logout')->assertOk();

    // regenerateToken(): a fresh CSRF token, never a blank one. Read before
    // any assertSessionMissing() below, which restarts the store and would
    // mint a token of its own — hiding the very line under test.
    expect(session()->token())->not->toBeNull()
        ->and(session()->token())->not->toBe('csrf-token-before-logout');

    // invalidate(): everything the signed-in session held is gone, not just
    // the auth key — a shared machine must not keep the last user's
    // half-finished checkout around for the next one.
    $response->assertSessionMissing('checkout_draft');

    $this->assertGuest('web');

    // And access really stops. Sanctum's RequestGuard caches the user it
    // resolved for the rest of the process, so the cached guards have to go
    // before asking again — otherwise this passes for the wrong reason.
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/me')->assertUnauthorized();
});

it('ignores an email or password change submitted to the profile endpoint', function () {
    $user = User::factory()->create(['email' => 'amina@example.es', 'password' => 'password123']);

    // A legitimate field travels in the same payload, so a green assertion
    // below cannot come from the request having been rejected wholesale.
    $this->actingAs($user)->patchJson('/api/me', [
        'locale' => 'ar',
        'email' => 'attaquant@example.es',
        'password' => 'attacker-password',
    ])
        ->assertOk()
        ->assertJsonPath('data.locale', 'ar')
        ->assertJsonPath('data.email', 'amina@example.es');

    $fresh = $user->fresh();

    // Widening updateMe's validate() to accept either of these is an account
    // takeover primitive: the address is where the reset link goes, and the
    // password is the credential itself.
    expect($fresh->email)->toBe('amina@example.es')
        ->and($fresh->locale)->toBe('ar')
        ->and(Hash::check('password123', $fresh->password))->toBeTrue()
        ->and(Hash::check('attacker-password', $fresh->password))->toBeFalse();

    $this->assertDatabaseMissing('users', ['email' => 'attaquant@example.es']);
});

it('requires a password long enough to be worth having', function () {
    // Ten characters, from one definition (Password::defaults) so register and
    // reset can never drift apart.
    $this->postJson('/api/register', [
        'name' => 'Trop Court',
        'email' => 'court@example.es',
        'phone' => '0551234567',
        'password' => 'court123',
        'password_confirmation' => 'court123',
    ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

    $this->postJson('/api/register', [
        'name' => 'Assez Long',
        'email' => 'long@example.es',
        'phone' => '0551234568',
        'password' => 'une-phrase-de-passe',
        'password_confirmation' => 'une-phrase-de-passe',
    ])->assertCreated();
});
