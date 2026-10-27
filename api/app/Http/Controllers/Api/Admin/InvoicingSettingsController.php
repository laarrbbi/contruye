<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\BusinessProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The business identity, kept in one place.
 *
 * The seller (emisor) block printed on every invoice — razón social, NIF and
 * domicilio, plus the legal form and Registro Mercantil entry of a company —
 * must be complete before anything is issued. The same block, plus the WhatsApp number and support
 * hours, feeds the public site through BusinessProfile, which exposes only the
 * identity and contact subset.
 */
class InvoicingSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(Invoice::sellerSettings());
    }

    public function update(Request $request): JsonResponse
    {
        // A number that cannot be dialled is refused here, not published as a
        // dead WhatsApp button on every page of the site.
        $dialable = function (string $attribute, mixed $value, \Closure $fail): void {
            if (is_string($value) && trim($value) !== '' && BusinessProfile::international($value) === null) {
                $fail(__('Enter a valid phone number, for example 612 34 56 78.'));
            }
        };

        $rules = collect(Invoice::SELLER_FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => ['nullable', 'string', 'max:500']])
            ->all();
        $rules['phone'][] = $dialable;
        $rules['whatsapp'][] = $dialable;
        $rules['email'][] = 'email';
        $rules['support_hours'] = ['nullable', 'string', 'max:120'];

        $validated = $request->validate($rules);

        $before = Invoice::sellerSettings()['seller'];

        $seller = [];
        foreach (Invoice::SELLER_FIELDS as $field) {
            $seller[$field] = trim((string) ($validated[$field] ?? ''));
        }

        Setting::setValue('invoicing.seller', $seller);

        // This block is printed on fiscal documents and on the public site: a
        // change to it must show what it was before.
        AuditLog::record($request->user(), 'invoicing.settings_updated', null, [
            'before' => $before,
            'after' => $seller,
        ]);

        return response()->json(Invoice::sellerSettings());
    }
}
