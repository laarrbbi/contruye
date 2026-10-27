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
     * any invoice can be issued — the mandatory mentions of the émetteur
     * under Algerian invoicing rules (décret exécutif 05-468).
     */
    public const REQUIRED_SELLER_FIELDS = [
        'business_name',
        'legal_form',
        'capital',
        'address',
        'rc',
        'nif',
        'nis',
        'ai',
    ];

    public const SELLER_FIELDS = [
        ...self::REQUIRED_SELLER_FIELDS,
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
        'buyer_rc',
        'buyer_nif',
        'buyer_nis',
        'buyer_ai',
        'seller',
        'lines',
        'tva_rate',
        'subtotal_ht_c',
        'discount_ht_c',
        'total_ht_c',
        'tva_c',
        'total_ttc_c',
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
            'tva_rate' => 'decimal:2',
            'subtotal_ht_c' => 'integer',
            'discount_ht_c' => 'integer',
            'total_ht_c' => 'integer',
            'tva_c' => 'integer',
            'total_ttc_c' => 'integer',
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
