<?php

use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Support\Facades\Http;

/*
 * Sign-up and password reset ask HaveIBeenPwned whether the password has
 * leaked. Laravel waits up to 30 seconds for the answer; behind a firewall
 * that silently drops outbound connections, every sign-up would hang that
 * long before going through. The wait is capped, and the check still fails
 * open: an unreachable service never blocks a buyer from signing up.
 */

it('waits at most five seconds for the leaked-password service', function () {
    $timeout = null;
    Http::fake(function ($request, array $options) use (&$timeout) {
        $timeout = $options['timeout'] ?? null;

        return Http::response('', 200);
    });

    app(UncompromisedVerifier::class)->verify(['value' => 'un mot de passe', 'threshold' => 0]);

    expect($timeout)->toBe(5);
});

it('lets the password through when the service cannot answer', function () {
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 503)]);

    expect(app(UncompromisedVerifier::class)->verify(['value' => 'un mot de passe', 'threshold' => 0]))->toBeTrue();
});

it('still refuses a password the service reports as leaked', function () {
    // SHA-1 of "password", split the way the range API answers.
    $hash = strtoupper(sha1('password'));
    Http::fake(['api.pwnedpasswords.com/range/'.substr($hash, 0, 5) => Http::response(substr($hash, 5).':3861493')]);

    expect(app(UncompromisedVerifier::class)->verify(['value' => 'password', 'threshold' => 0]))->toBeFalse();
});
