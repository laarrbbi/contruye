<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    /**
     * How long a CCP / BaridiMob receipt may wait for an admin before the
     * dashboard flags it. The buyer has already sent the money.
     */
    public const RECEIPT_REVIEW_HOURS = 48;

    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'coupon_id',
        'amount',
        'discount_percent',
        'pricing_snapshot',
        'currency',
        'method',
        'scope',
        'status',
        'chargily_checkout_id',
        'stripe_session_id',
        'receipt_path',
        'receipt_uploaded_at',
        'admin_note',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'pricing_snapshot' => 'array',
            'paid_at' => 'datetime',
            'receipt_uploaded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function enrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function orderModules(): HasMany
    {
        return $this->hasMany(OrderModule::class);
    }

    /**
     * Store a CCP / BaridiMob receipt and put the order in the review queue.
     * A receipt re-sent after a rejection takes the order out of `failed`.
     */
    public function attachReceipt(string $path): void
    {
        DB::transaction(function () use ($path): void {
            $fresh = self::query()->lockForUpdate()->findOrFail($this->id);

            if ($fresh->status === 'failed') {
                $fresh->reclaimCouponUse();
            }

            $fresh->update([
                'receipt_path' => $path,
                'receipt_uploaded_at' => now(),
                'status' => 'awaiting_review',
                'admin_note' => null,
            ]);
        });

        $this->refresh();
    }

    /**
     * A failed order gave its coupon use back. Leaving `failed` (a receipt
     * re-sent, a late gateway confirmation, an admin grant) means it holds
     * the use again — unconditionally: the buyer transferred the discounted
     * amount, and a cap reached in the meantime cannot undo that.
     */
    private function reclaimCouponUse(): void
    {
        if ($this->coupon_id !== null) {
            Coupon::query()->whereKey($this->coupon_id)->increment('uses');
        }
    }

    /**
     * Mark the order failed and hand its reserved coupon use back, so an
     * abandoned or refused payment does not consume a limited coupon.
     */
    public function markFailed(): void
    {
        DB::transaction(function (): void {
            // Under a row lock, and only from a state that still holds a use:
            // a gateway re-delivers its "failed" event, and every repeat used
            // to hand the coupon use back again — a capped coupon gained one
            // extra use per replay.
            $fresh = self::query()->lockForUpdate()->find($this->id);

            if ($fresh === null || ! in_array($fresh->status, ['pending', 'awaiting_review'], true)) {
                return;
            }

            $fresh->update(['status' => 'failed']);
            $fresh->coupon?->releaseUse();
        });

        $this->refresh();
    }

    /**
     * Transition the order to paid and grant the enrollment, atomically and
     * idempotently. This is the ONLY code path that creates enrollments —
     * used by both the Chargily webhook and manual admin approval, either of
     * which may fire more than once for the same order.
     *
     * @return bool true if the transition happened, false if it was already paid
     */
    public function markPaidAndEnroll(): bool
    {
        return DB::transaction(function (): bool {
            // Re-read under a row lock so two concurrent deliveries serialize.
            $fresh = self::query()->lockForUpdate()->find($this->id);

            if ($fresh === null || $fresh->status === 'paid') {
                return false;
            }

            if ($fresh->status === 'failed') {
                $fresh->reclaimCouponUse();
            }

            $fresh->forceFill([
                'status' => 'paid',
                'paid_at' => $fresh->paid_at ?? now(),
            ])->save();

            if ($fresh->scope === 'modules') {
                // One enrollment per purchased module; skip anything the user
                // already owns (repeat webhooks, overlapping orders). Insert
                // first and fall back to the existing row on the unique index,
                // so two orders settling at once cannot both insert.
                foreach ($fresh->orderModules()->pluck('module_id') as $moduleId) {
                    Enrollment::query()->createOrFirst(
                        [
                            'user_id' => $fresh->user_id,
                            'course_id' => $fresh->course_id,
                            'module_id' => $moduleId,
                        ],
                        ['order_id' => $fresh->id],
                    );
                }
            } else {
                Enrollment::query()->createOrFirst(
                    [
                        'user_id' => $fresh->user_id,
                        'course_id' => $fresh->course_id,
                        'module_id' => null,
                    ],
                    ['order_id' => $fresh->id],
                );
            }

            $this->refresh();

            return true;
        });
    }
}
