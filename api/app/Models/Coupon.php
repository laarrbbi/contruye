<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'percent_off',
        'max_uses',
        'uses',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'percent_off' => 'integer',
            'max_uses' => 'integer',
            'uses' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function isRedeemable(): bool
    {
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return $this->max_uses === null || $this->uses < $this->max_uses;
    }

    public function apply(int $amount): int
    {
        return (int) round($amount * (100 - $this->percent_off) / 100);
    }

    /**
     * Claim one use, atomically, and report whether the cap allowed it.
     *
     * The cap has to be claimed when the discount is GRANTED, not when the
     * order is later paid: manual CCP orders sit pending for days, so counting
     * at payment let an unlimited number of buyers hold the same discounted
     * price while `uses` stayed at 1. The conditional WHERE means two
     * simultaneous claims serialize on the row and only one can win.
     */
    public function reserveUse(): bool
    {
        return self::query()
            ->whereKey($this->id)
            ->where(fn ($query) => $query->whereNull('max_uses')->orWhereColumn('uses', '<', 'max_uses'))
            ->increment('uses') > 0;
    }

    /** Give a reserved use back when the order it was claimed for never completes. */
    public function releaseUse(): void
    {
        self::query()->whereKey($this->id)->where('uses', '>', 0)->decrement('uses');
    }
}
