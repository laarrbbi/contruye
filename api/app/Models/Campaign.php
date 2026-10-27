<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A time-limited promotion on a course.
 *
 * The discount is applied server-side by PricingService, and `ends_at` is a
 * real, fixed deadline: the countdown students see is this timestamp, it does
 * not restart per visitor, and once it passes the price returns to normal
 * because the campaign simply stops being active.
 */
class Campaign extends Model
{
    use HasFactory, HasLocalizedAttributes;

    protected $fillable = [
        'course_id',
        'label_es',
        'label_en',
        'percent_off',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'percent_off' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** Enabled, already started, and not yet finished. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('ends_at', '>', now())
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()));
    }

    public function isRunning(): bool
    {
        return $this->is_active
            && $this->ends_at->isFuture()
            && ($this->starts_at === null || $this->starts_at->isPast());
    }

    public function localizedLabel(?string $locale = null): string
    {
        return (string) $this->localizedValue('label', $locale);
    }

    public function apply(int $amount): int
    {
        return (int) round($amount * (100 - $this->percent_off) / 100);
    }
}
