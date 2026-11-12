<?php

namespace App\Support\Account;

/**
 * A browser and a system named from a User-Agent header, enough for a person
 * to recognise their own devices in a list ("Safari · iPhone"). Order
 * matters: Edge and Opera also say "Chrome", Chrome also says "Safari".
 */
final class UserAgent
{
    public static function browser(?string $agent): ?string
    {
        if ($agent === null || $agent === '') {
            return null;
        }

        return match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'SamsungBrowser/') => 'Samsung Internet',
            str_contains($agent, 'Firefox/') || str_contains($agent, 'FxiOS/') => 'Firefox',
            str_contains($agent, 'CriOS/') || str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => null,
        };
    }

    public static function os(?string $agent): ?string
    {
        if ($agent === null || $agent === '') {
            return null;
        }

        return match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS X') || str_contains($agent, 'Macintosh') => 'macOS',
            str_contains($agent, 'CrOS') => 'ChromeOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };
    }
}
