<?php

/**
 * `php artisan app:check-secrets --strict` is the deploy-time answer to S1/S2:
 * a blank HMAC secret does not disable a payment provider, it makes its
 * signature check pass for anything an attacker can compute. WebhookForgeryTest
 * proves the runtime refusal; this proves the deploy never gets that far.
 *
 * It is also the answer to the go-live trap: a Stripe test-mode key in
 * production takes test payments and collects nothing.
 */

/** Every secret the command is expected to guard, and a plausible value. */
function securityCriticalSecrets(): array
{
    return [
        'services.stripe.webhook_secret' => 'whsec_live',
        'services.stripe.secret' => 'sk_live_dummy',
        'services.bunny.token_key' => 'bunny_token_key',
        'services.bunny.library_id' => '123456',
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
    ];
}

/** The settings a live site needs, and a value that satisfies each. */
function productionSettings(): array
{
    return [
        'app.env' => 'production',
        'app.debug' => false,
        'session.secure' => true,
        'app.url' => 'https://api.construye.example',
        'app.frontend_url' => 'https://construye.example',
        'mail.default' => 'smtp',
    ];
}

function productionReady(): void
{
    config(securityCriticalSecrets() + productionSettings());
}

it('passes once every secret is set and every setting is live', function () {
    productionReady();

    $this->artisan('app:check-secrets --strict')
        ->expectsOutputToContain('All security-critical secrets are set.')
        ->expectsOutputToContain('Production settings are live.')
        ->assertExitCode(0);
});

it('refuses to pass --strict while any one secret is blank', function () {
    // One at a time: adding a sixth secret to the list must be a deliberate
    // act, and dropping any of these five must not go unnoticed.
    foreach (array_keys(securityCriticalSecrets()) as $blank) {
        productionReady();
        config([$blank => '']);

        $this->artisan('app:check-secrets --strict')
            ->expectsOutputToContain($blank)
            ->expectsOutputToContain('Refusing to pass')
            ->assertExitCode(1);
    }
});

it('treats whitespace as blank, because a stray space is not a secret', function () {
    productionReady();
    config(['services.stripe.webhook_secret' => '   ']);

    $this->artisan('app:check-secrets --strict')->assertExitCode(1);
});

it('reports without failing when --strict is not asked for', function () {
    productionReady();
    config(['services.stripe.secret' => '', 'services.bunny.token_key' => '']);

    // The bare command is a report a developer can run locally without a
    // full production .env; only the deploy gate is allowed to fail.
    $this->artisan('app:check-secrets')
        ->expectsOutputToContain('2 secret(s) are blank:')
        ->assertExitCode(0);
});

it('names every blank secret at once rather than the first one it finds', function () {
    productionReady();

    foreach (array_keys(securityCriticalSecrets()) as $key) {
        config([$key => '']);
    }

    $command = $this->artisan('app:check-secrets --strict');

    foreach (array_keys(securityCriticalSecrets()) as $key) {
        $command->expectsOutputToContain($key);
    }

    // A deploy that fixes one secret per run is a deploy nobody finishes.
    $command->expectsOutputToContain('5 secret(s) are blank:')->assertExitCode(1);
});

it('refuses a production setting that is wrong for a live site', function (array $override, string $named) {
    productionReady();
    config($override);

    $this->artisan('app:check-secrets --strict')
        ->expectsOutputToContain($named)
        ->expectsOutputToContain('Refusing to pass')
        ->assertExitCode(1);
})->with([
    'not production' => [['app.env' => 'local'], 'APP_ENV'],
    'debug on' => [['app.debug' => true], 'APP_DEBUG'],
    'insecure cookie' => [['session.secure' => false], 'SESSION_SECURE_COOKIE'],
    'http api url' => [['app.url' => 'http://api.construye.example'], 'APP_URL'],
    'http frontend url' => [['app.frontend_url' => 'http://construye.example'], 'FRONTEND_URL'],
    'mail to the log' => [['mail.default' => 'log'], 'MAIL_MAILER'],
    'stripe test key' => [['services.stripe.secret' => 'sk_test_abc'], 'STRIPE_SECRET'],
]);

it('lets a transfer-and-Bizum launch pass once card payments are switched off on purpose', function () {
    productionReady();
    config([
        'services.stripe.enabled' => false,
        'services.stripe.secret' => '',
        'services.stripe.webhook_secret' => '',
    ]);

    $this->artisan('app:check-secrets --strict')
        ->expectsOutputToContain('Checkout methods offered: manual_transfer, manual_bizum')
        ->assertExitCode(0);
});

it('does not treat blank gateway keys as switched off', function () {
    productionReady();
    config(['services.stripe.secret' => '', 'services.stripe.webhook_secret' => '']);

    $this->artisan('app:check-secrets --strict')
        ->expectsOutputToContain('services.stripe.secret')
        ->assertExitCode(1);
});

it('refuses a deploy where nobody could pay by any method', function () {
    productionReady();
    config([
        'services.stripe.enabled' => false,
        'services.manual_payment.iban' => '',
        'services.manual_payment.bizum_phone' => '',
    ]);

    $this->artisan('app:check-secrets --strict')
        ->expectsOutputToContain('nobody can buy')
        ->assertExitCode(1);
});
