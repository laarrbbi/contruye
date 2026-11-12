<?php

namespace App\Support\Protection;

use App\Models\User;

/**
 * An invisible mark of the reader in the course text they are served, so a
 * copy that turns up elsewhere names the account it came from.
 *
 * The mark is the user's id and a short keyed check, written in characters
 * that take no space and change nothing in the layout (U+2061…U+2063,
 * invisible operators): a start sign, then one character per bit. It goes
 * after the first space of each paragraph, list item and table cell, at a
 * word boundary, so no word is cut, no Arabic letter loses its join and no
 * line breaks differently. It survives copy and paste, which is the point;
 * `php artisan content:trace` reads it back.
 *
 * It is a trace, not a lock: rewriting the text by hand removes it, as
 * nothing on a screen can be kept from being retyped.
 */
final class ContentFingerprint
{
    private const START = "\u{2061}";

    private const ZERO = "\u{2062}";

    private const ONE = "\u{2063}";

    private const ID_BITS = 24;

    private const CHECK_BITS = 8;

    public static function enabled(): bool
    {
        return (bool) config('protection.fingerprint', true);
    }

    public static function stamp(?string $html, User $user): ?string
    {
        if ($html === null || $html === '' || ! self::enabled()) {
            return $html;
        }

        $mark = self::mark($user->id);

        return (string) preg_replace_callback(
            '/(<(?:p|li|td)(?:\s[^>]*)?>)([^<\s][^< ]*) /u',
            fn (array $m) => $m[1].$m[2].' '.$mark,
            $html,
        );
    }

    public static function mark(int $userId): string
    {
        $bits = str_pad(decbin($userId & ((1 << self::ID_BITS) - 1)), self::ID_BITS, '0', STR_PAD_LEFT)
            .str_pad(decbin(self::check($userId)), self::CHECK_BITS, '0', STR_PAD_LEFT);

        return self::START.strtr($bits, ['0' => self::ZERO, '1' => self::ONE]);
    }

    /**
     * The user ids marked in a piece of text, most frequent first. A mark
     * whose check does not match (damaged, or forged) is ignored.
     *
     * @return array<int, int> user id => times found
     */
    public static function trace(string $text): array
    {
        $length = self::ID_BITS + self::CHECK_BITS;
        $pattern = '/'.self::START.'((?:'.self::ZERO.'|'.self::ONE.'){'.$length.'})/u';

        preg_match_all($pattern, $text, $matches);

        $found = [];
        foreach ($matches[1] as $encoded) {
            $bits = strtr($encoded, [self::ZERO => '0', self::ONE => '1']);
            $id = bindec(substr($bits, 0, self::ID_BITS));
            $check = bindec(substr($bits, self::ID_BITS));

            if (is_int($id) && $check === self::check($id)) {
                $found[$id] = ($found[$id] ?? 0) + 1;
            }
        }

        arsort($found);

        return $found;
    }

    private static function check(int $userId): int
    {
        return ord(hash_hmac('sha256', 'fingerprint:'.$userId, (string) config('app.key'), true)[0]);
    }
}
