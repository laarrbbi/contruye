<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Simulate requests coming from the SPA so Sanctum's stateful
        // middleware attaches the session, as it does in the browser.
        $this->withHeader('Origin', config('app.frontend_url'));

        // Every checkout method configured with dummy credentials, so a test
        // exercises the checkout rules rather than the absence of keys.
        // phpunit.xml pins the real ones blank; a test about a missing key
        // blanks it again itself.
        config([
            'services.stripe.secret' => 'sk_test_dummy',
            'services.stripe.webhook_secret' => 'whsec_dummy',
            'services.manual_payment.iban' => 'ES00 0000 0000 0000 0000 0000',
            'services.manual_payment.holder' => '[DEMO] Titular',
            'services.manual_payment.bizum_phone' => '600000000',
        ]);
    }
}
