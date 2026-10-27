<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared payload for issuing and correcting an invoice. The buyer block is
 * what the décret requires: name + address always; RC/NIF/NIS/AI only when
 * the buyer is a business.
 */
class InvoicePayloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route group already enforces admin.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'buyer_name' => ['required', 'string', 'max:190'],
            'buyer_address' => ['required', 'string', 'max:1000'],
            'buyer_rc' => ['nullable', 'string', 'max:100'],
            'buyer_nif' => ['nullable', 'string', 'max:100'],
            'buyer_nis' => ['nullable', 'string', 'max:100'],
            'buyer_ai' => ['nullable', 'string', 'max:100'],
            // Algerian TVA rates: 19 % (normal), 9 % (réduit), 0 % (exonéré).
            'tva_rate' => ['required', 'numeric', 'in:0,9,19'],
            // Bounded so a typo can't burn a future year's gap-free sequence;
            // 60 days back still covers a January invoice dated December.
            'issued_at' => [
                'nullable',
                'date_format:Y-m-d',
                // "Today" in Algiers, the calendar the invoice is dated in.
                'before_or_equal:'.now(config('app.business_timezone'))->toDateString(),
                'after_or_equal:'.now(config('app.business_timezone'))->subDays(60)->toDateString(),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
