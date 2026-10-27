<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared payload for issuing and correcting an invoice. The buyer block is
 * what the Reglamento de facturación asks for: name + address always; the
 * NIF when the buyer is a business or a professional.
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
            'buyer_nif' => ['nullable', 'string', 'max:100'],
            // Spanish IVA rates: 21 % (general), 10 % (reducido), 4 %
            // (superreducido), 0 % (exento, e.g. art. 20 Ley 37/1992).
            'iva_rate' => ['required', 'numeric', 'in:0,4,10,21'],
            // Bounded so a typo can't burn a future year's gap-free sequence;
            // 60 days back still covers a January invoice dated December.
            'issued_at' => [
                'nullable',
                'date_format:Y-m-d',
                // "Today" in Madrid, the calendar the invoice is dated in.
                'before_or_equal:'.now(config('app.business_timezone'))->toDateString(),
                'after_or_equal:'.now(config('app.business_timezone'))->subDays(60)->toDateString(),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
