<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Accept any standards-compliant header ('es-ES', 'en-GB,es;q=0.8', …):
        // keep only the primary subtag of the first (highest-priority) tag.
        $header = (string) $request->header('Accept-Language', 'es');
        $primary = strtolower(substr(trim(explode(',', $header)[0]), 0, 2));

        $supported = config('locales.supported', ['es']);
        $default = config('locales.default', 'es');

        app()->setLocale(in_array($primary, $supported, true) ? $primary : $default);

        return $next($request);
    }
}
