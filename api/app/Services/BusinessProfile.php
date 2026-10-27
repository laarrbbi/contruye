<?php

namespace App\Services;

use App\Models\Invoice;

/**
 * The business behind the site, as the public is allowed to see it.
 *
 * One source of truth: the seller block the admin fills for invoicing. What
 * goes on a public page is a deliberate subset — identity and contact, never
 * the RIB, the NIS or the article d'imposition, which belong on fiscal
 * documents and nowhere else.
 *
 * Empty fields come back as null so the front-end renders nothing for them.
 * Nothing here is ever defaulted: a phone number that has not been entered
 * does not exist, and the site must not pretend otherwise.
 */
class BusinessProfile
{
    /** Fields the public site may show, in display order. */
    public const PUBLIC_FIELDS = [
        'business_name',
        'legal_form',
        'capital',
        'address',
        'rc',
        'nif',
        'phone',
        'whatsapp',
        'email',
        'support_hours',
    ];

    /**
     * @return array<string, string|null>
     */
    public static function public(): array
    {
        $seller = Invoice::sellerSettings()['seller'];

        $profile = [];
        foreach (self::PUBLIC_FIELDS as $field) {
            $value = trim((string) ($seller[$field] ?? ''));
            $profile[$field] = $value === '' ? null : $value;
        }

        // Ready-made links, so no client has to know how an Algerian number
        // becomes an international one.
        $whatsapp = self::international($profile['whatsapp']);
        $phone = self::international($profile['phone']);

        $profile['whatsapp_url'] = $whatsapp !== null ? "https://wa.me/{$whatsapp}" : null;
        $profile['phone_tel'] = $phone !== null ? "tel:+{$phone}" : null;

        return $profile;
    }

    /**
     * An Algerian (or already international) number as bare international
     * digits — "0550 12 34 56" becomes 213550123456 — or null when it cannot
     * be a real number.
     */
    public static function international(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $raw = trim($raw);
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($raw, '+')) {
            // Already international: the digits after '+' are the number.
        } elseif (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '213'.substr($digits, 1);
        }

        // Shortest real international number is 8 digits; E.164 caps at 15.
        // An Algerian mobile is 213 + 9 digits.
        $length = strlen($digits);

        return $length >= 11 && $length <= 15 ? $digits : null;
    }
}
