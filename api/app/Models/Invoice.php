<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    /**
     * The seller fields that must be filled in the invoicing settings before
     * any invoice can be issued — what the Reglamento de facturación (Real
     * Decreto 1619/2012, art. 6) requires of the issuer: name or company
     * name, NIF and address.
     */
    public const REQUIRED_SELLER_FIELDS = [
        'business_name',
        'nif',
        'address',
    ];

    public const SELLER_FIELDS = [
        ...self::REQUIRED_SELLER_FIELDS,
        // A company (S.L., S.A.) prints its form and its Registro Mercantil
        // entry on every document; a sole trader leaves both blank.
        'legal_form',
        'registry',
        // Only when the owner chooses to show it; never required.
        'capital',
        'phone',
        'email',
        'bank_details',
        // Public contact channels. Optional for invoicing; shown on the site
        // only once filled (see BusinessProfile).
        'whatsapp',
        'support_hours',
    ];

    protected $fillable = [
        'order_id',
        'number',
        'year',
        'sequence',
        'issued_at',
        'buyer_name',
        'buyer_address',
        'buyer_nif',
        'seller',
        'lines',
        'iva_rate',
        'subtotal_net_c',
        'discount_net_c',
        'total_net_c',
        'iva_c',
        'total_gross_c',
        'currency',
        'payment_method_label',
        'payment_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'payment_date' => 'date',
            'seller' => 'array',
            'lines' => 'array',
            'iva_rate' => 'decimal:2',
            'subtotal_net_c' => 'integer',
            'discount_net_c' => 'integer',
            'total_net_c' => 'integer',
            'iva_c' => 'integer',
            'total_gross_c' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Allocate the next gap-free number for the given year, serialized under
     * a row lock so two admins issuing at the same time can never collide.
     * Must be called inside a transaction.
     *
     * @return array{number: string, year: int, sequence: int}
     */
    public static function allocateNumber(int $year): array
    {
        $last = self::query()
            ->where('year', $year)
            ->lockForUpdate()
            ->max('sequence');

        $sequence = ((int) $last) + 1;

        return [
            'number' => sprintf('FAC-%d-%05d', $year, $sequence),
            'year' => $year,
            'sequence' => $sequence,
        ];
    }

    /**
     * @return array{complete: bool, missing: list<string>, seller: array<string, string>}
     */
    public static function sellerSettings(): array
    {
        $raw = Setting::getValue('invoicing.seller');

        $seller = [];
        foreach (self::SELLER_FIELDS as $field) {
            $seller[$field] = trim((string) ($raw[$field] ?? ''));
        }

        $missing = array_values(array_filter(
            self::REQUIRED_SELLER_FIELDS,
            fn (string $field) => $seller[$field] === '',
        ));

        return [
            'complete' => $missing === [],
            'missing' => $missing,
            'seller' => $seller,
        ];
    }
}
