<?php

namespace App\Support\Account;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The devices a user is signed in on: the rows of the database session store
 * that carry their id. A session's id is the cookie that signs in, so it never
 * leaves the server; each row goes out under a keyed hash of it instead, which
 * names the row and cannot be turned back into the cookie.
 *
 * Only the database store keeps a list to read. With another driver (the
 * tests' array store) there is nothing to list and nothing to close.
 */
final class DeviceSessions
{
    public static function supported(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * @return Collection<int, array{id: string, browser: string|null, os: string|null, ip: string|null, last_active_at: string, current: bool}>
     */
    public static function list(User $user, ?string $currentId): Collection
    {
        if (! self::supported()) {
            return collect();
        }

        return self::rows($user)
            ->map(fn (object $row) => [
                'id' => self::publicId($row->id),
                'browser' => UserAgent::browser($row->user_agent),
                'os' => UserAgent::os($row->user_agent),
                'ip' => $row->ip_address,
                'last_active_at' => Carbon::createFromTimestamp($row->last_activity)->toISOString(),
                'current' => $currentId !== null && hash_equals($row->id, $currentId),
            ])
            ->values();
    }

    /** Close one session by its public id; never the current one. */
    public static function close(User $user, string $publicId, ?string $currentId): bool
    {
        if (! self::supported()) {
            return false;
        }

        $row = self::rows($user)->first(fn (object $row) => hash_equals(self::publicId($row->id), $publicId));

        if ($row === null || ($currentId !== null && hash_equals($row->id, $currentId))) {
            return false;
        }

        return self::table()->where('id', $row->id)->delete() > 0;
    }

    /** Close every session but the current one. */
    public static function closeOthers(User $user, ?string $currentId): int
    {
        if (! self::supported()) {
            return 0;
        }

        return self::table()
            ->where('user_id', $user->id)
            ->when($currentId !== null, fn ($query) => $query->where('id', '!=', $currentId))
            ->delete();
    }

    /**
     * Keep at most $max sessions: the oldest by last activity are closed, the
     * current one never. Run after a sign-in, so one account cannot be read on
     * many screens at once.
     */
    public static function keepNewest(User $user, int $max, ?string $currentId): int
    {
        if (! self::supported() || $max < 1) {
            return 0;
        }

        $others = self::rows($user)
            ->reject(fn (object $row) => $currentId !== null && hash_equals($row->id, $currentId))
            ->values();

        // The current session takes one of the places.
        $excess = $others->slice(max(0, $max - 1));

        if ($excess->isEmpty()) {
            return 0;
        }

        return self::table()->whereIn('id', $excess->pluck('id'))->delete();
    }

    public static function publicId(string $sessionId): string
    {
        return substr(hash_hmac('sha256', $sessionId, (string) config('app.key')), 0, 24);
    }

    /** @return Collection<int, object> newest first */
    private static function rows(User $user): Collection
    {
        return self::table()
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']);
    }

    private static function table(): Builder
    {
        return DB::connection(config('session.connection'))->table(config('session.table', 'sessions'));
    }
}
