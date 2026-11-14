<?php

namespace App\Providers;

use App\Services\Assistant\AnthropicLlm;
use App\Services\Assistant\Llm;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\NotPwnedVerifier;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Sign-up and password reset ask HaveIBeenPwned about the password.
        // Laravel waits 30 s for the answer; behind a firewall that drops the
        // connection, every sign-up would hang that long. It still fails open.
        // extend(), not singleton(): the validation provider is deferred and
        // would replace a plain binding when it loads.
        $this->app->extend(
            UncompromisedVerifier::class,
            fn ($verifier, $app) => new NotPwnedVerifier($app[HttpFactory::class], 5),
        );

        // Nicolás's model: Claude through the official SDK. The tests bind a fake.
        $this->app->singleton(Llm::class, AnthropicLlm::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A proxy that forgets X-Forwarded-Proto would otherwise hand the SPA
        // http:// download links; in production the scheme is not negotiable.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Ten characters, and not one of the billions already leaked. Length
        // over composition rules: a non-technical audience answers those with
        // Password1!, which is in every breach list anyway. The breach check
        // is a call to HaveIBeenPwned, so it stays out of the test suite.
        Password::defaults(function () {
            $rule = Password::min(10);

            return $this->app->runningUnitTests() ? $rule : $rule->uncompromised();
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Nicolás: questions per student, per minute and per day (credits are the monthly ceiling).
        RateLimiter::for('assistant', function (Request $request) {
            $key = 'assistant:'.($request->user()?->id ?: $request->ip());

            return [
                Limit::perMinute((int) config('assistant.limits.per_minute'))->by($key.':m'),
                Limit::perDay((int) config('assistant.limits.per_day'))->by($key.':d'),
            ];
        });
    }
}
