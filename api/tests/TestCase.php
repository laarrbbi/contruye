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
            'services.chargily.api_key' => 'test_pk_dummy',
            'services.chargily.secret' => 'test_sk_dummy',
            'services.stripe.secret' => 'sk_test_dummy',
            'services.stripe.webhook_secret' => 'whsec_dummy',
            'services.manual_payment.ccp_number' => '0000000000 00',
            'services.manual_payment.ccp_name' => '[DÉMO] Titulaire',
            'services.manual_payment.baridimob_rip' => '00799999000000000000',
        ]);
    }
}
