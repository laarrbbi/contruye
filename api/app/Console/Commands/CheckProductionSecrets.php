<?php

namespace App\Console\Commands;

use App\Services\PaymentMethods;
use Illuminate\Console\Command;

/**
 * The deploy gate: fails when a secret is blank or a setting is not the one
 * production needs.
 *
 * A blank webhook secret does not disable a payment provider — it makes its
 * signature check pass for anything, because HMAC with an empty key is a value
 * anyone can compute. The services now refuse a blank secret at runtime; this
 * command is how a deploy finds out BEFORE going live.
 *
 * A gateway the owner has not signed up for yet is switched off explicitly
 * (STRIPE_ENABLED=false). Blank keys are not "off".
 *
 *   php artisan app:check-secrets            # report
 *   php artisan app:check-secrets --strict   # non-zero exit for CI / deploy
 */
class CheckProductionSecrets extends Command
{
    protected $signature = 'app:check-secrets {--strict}';

    protected $description = 'Verify that no security-critical secret is blank and production settings are live';

    /** @var array<string, string> config key => what breaks when it is blank */
    private const ALWAYS = [
        'services.bunny.token_key' => 'Lesson videos cannot be signed — playback returns 503.',
        'services.bunny.library_id' => 'Lesson videos cannot be resolved — playback returns 503.',
        'app.key' => 'Sessions and encrypted cookies are not secure.',
    ];

    private const STRIPE = [
        'services.stripe.webhook_secret' => 'Stripe webhook signatures cannot be verified — the endpoint refuses every call.',
        'services.stripe.secret' => 'Stripe checkouts cannot be created.',
    ];

    public function handle(): int
    {
        $blank = $this->blankSecrets();
        $settings = $this->productionProblems();

        if ($blank === [] && $settings === []) {
            $this->info('All security-critical secrets are set.');
            $this->info('Production settings are live.');
            $this->line('Checkout methods offered: '.implode(', ', PaymentMethods::available()));

            return self::SUCCESS;
        }

        if ($blank !== []) {
            $this->warn(count($blank).' secret(s) are blank:');
            $this->table(['Config key', 'Consequence'], $blank);
        }

        if ($settings !== []) {
            $this->warn(count($settings).' setting(s) are not production-ready:');
            $this->table(['Setting', 'Problem'], $settings);
        }

        if ($this->option('strict')) {
            $this->error('Refusing to pass: fix these before serving traffic.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function blankSecrets(): array
    {
        $required = self::ALWAYS
            + (PaymentMethods::stripeEnabled() ? self::STRIPE : []);

        $blank = [];

        foreach ($required as $key => $consequence) {
            if (trim((string) config($key)) === '') {
                $blank[] = [$key, $consequence];
            }
        }

        return $blank;
    }

    /**
     * Settings that are fine on a laptop and wrong on the live site. Each one
     * is a way to take money without noticing something is off.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function productionProblems(): array
    {
        $problems = [];

        if (config('app.env') !== 'production') {
            $problems[] = ['APP_ENV', 'Is "'.config('app.env').'", not "production".'];
        }

        if (config('app.debug')) {
            $problems[] = ['APP_DEBUG', 'Is on: an error page would show the stack trace and environment to any visitor.'];
        }

        if (! config('session.secure')) {
            $problems[] = ['SESSION_SECURE_COOKIE', 'Is off: the session cookie would also travel over plain HTTP.'];
        }

        foreach (['app.url' => 'APP_URL', 'app.frontend_url' => 'FRONTEND_URL'] as $key => $name) {
            if (! str_starts_with((string) config($key), 'https://')) {
                $problems[] = [$name, 'Is not an https:// URL: payment return links and invoice links would be insecure.'];
            }
        }

        if (in_array(config('mail.default'), ['log', 'array', null, ''], true)) {
            $problems[] = ['MAIL_MAILER', 'Is "'.config('mail.default').'": confirmations, invoices and password resets would never leave the server.'];
        }

        if (PaymentMethods::available() === []) {
            $problems[] = ['Checkout', 'No payment method is configured: nobody can buy. Fill the bank transfer details at least.'];
        }

        if (PaymentMethods::stripeEnabled()) {
            $secret = (string) config('services.stripe.secret');

            if (str_starts_with($secret, 'sk_test_') || str_starts_with($secret, 'rk_test_')) {
                $problems[] = ['STRIPE_SECRET', 'Is a test-mode key.'];
            }
        }

        return $problems;
    }
}
