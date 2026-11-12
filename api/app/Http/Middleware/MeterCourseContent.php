<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * The meter on every route that serves course material (a lesson, a
 * module's texts, a document, a province's guide, a quiz, a download).
 *
 * - A ceiling per minute and per hour, per student: reading never comes near
 *   it, a script walking the whole course does, and gets a 429 that says why.
 * - Distinct pages read in 24 hours: above the alert line the owner gets one
 *   audit entry per day ("content.unusual_reading"), with the count, so an
 *   account being emptied is seen. Nothing is blocked for that alone.
 * - Every answer is marked private and not to be stored: the material does
 *   not stay in a shared cache or on the disk of a borrowed computer.
 *
 * Admins are not metered: they write the material.
 */
class MeterCourseContent
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null && ! $user->isAdmin()) {
            $minute = 'content:m:'.$user->id;
            $hour = 'content:h:'.$user->id;

            if (RateLimiter::tooManyAttempts($minute, config('protection.per_minute'))
                || RateLimiter::tooManyAttempts($hour, config('protection.per_hour'))) {
                $this->recordOnce($user, 'content.rate_limited', 'hour', ['path' => $request->path()]);

                $retry = RateLimiter::tooManyAttempts($hour, config('protection.per_hour'))
                    ? RateLimiter::availableIn($hour)
                    : RateLimiter::availableIn($minute);

                return response()->json([
                    'message' => __('You have opened a lot of course pages in a short time. Wait a few minutes and carry on.'),
                ], 429, ['Retry-After' => (string) $retry]);
            }

            RateLimiter::hit($minute, 60);
            RateLimiter::hit($hour, 3600);
            $this->countDistinct($user, $request);
        }

        $response = $next($request);

        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    /** One entry per student and kind per window, never a flood. */
    private function recordOnce(User $user, string $action, string $window, array $meta): void
    {
        $ttl = $window === 'hour' ? 3600 : 86400;

        if (Cache::add("content:audit:{$action}:{$user->id}", true, $ttl)) {
            AuditLog::record($user, $action, $user, $meta);
        }
    }

    private function countDistinct(User $user, Request $request): void
    {
        $day = now()->format('Y-m-d');
        $key = "content:pages:{$user->id}:{$day}";

        /** @var array<string, true> $pages */
        $pages = Cache::get($key, []);
        $pages[$request->path()] = true;
        Cache::put($key, $pages, now()->addDay());

        if (count($pages) > config('protection.daily_alert')) {
            $this->recordOnce($user, 'content.unusual_reading', 'day', ['pages_today' => count($pages)]);
        }
    }
}
