<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Public pages for search engines and link previews, without the
        // session middleware the web group would add.
        then: function () {
            Route::middleware([])->group(base_path('routes/seo.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind a TLS terminator, PHP sees plain HTTP: url() then builds
        // http:// links for invoices and receipts, which the browser blocks as
        // mixed content. Private ranges only — never '*', which would let a
        // caller forge its own scheme and client IP.
        $middleware->trustProxies(
            at: ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->statefulApi();

        // A browser following a link to a protected file (an invoice PDF, a
        // receipt) after its session expired was sent to route('login'), which
        // an API-only app does not have: a 500. It now lands on the SPA's sign-in
        // page; JSON callers still get their 401.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->expectsJson()
            ? null
            : rtrim((string) config('app.frontend_url'), '/').'/login');

        $middleware->api(append: [
            SetLocale::class,
            // The 'api' limiter (120/min, per user or IP) was defined in
            // AppServiceProvider but attached to no route — every endpoint
            // without its own throttle was unlimited.
            'throttle:api',
        ]);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A bare abort(403) / abort(404) sent {"message": ""} in production,
        // and a missing route model leaked "No query results for model
        // [App\\Models\\Order] 5". Explicit messages pass through untouched.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $message = $e->getMessage();

            if ($message !== '' && ! str_starts_with($message, 'No query results for model')) {
                return null;
            }

            $generic = match ($e->getStatusCode()) {
                401 => __('Unauthenticated.'),
                403 => __('You do not have access to this.'),
                404 => __('Not found.'),
                default => null,
            };

            return $generic === null ? null : response()->json(['message' => $generic], $e->getStatusCode(), $e->getHeaders());
        });

        // Laravel's built-in 429 body is hardcoded English ("Too Many
        // Attempts."); render it localized for API consumers instead.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            return response()->json([
                'message' => __('Too many attempts. Please try again in a moment.'),
            ], 429, $e->getHeaders());
        });
    })
    ->create();
