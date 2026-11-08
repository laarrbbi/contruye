<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of the three offers of the course: after the purchase, at the first
 * milestone, at the end. The slot exists from the start; it reaches students
 * only once the admin has written it and switched it on (isLive).
 */
class Offer extends Model
{
    use HasLocalizedAttributes;

    /** In the order a student meets them; the later one wins when several are open. */
    public const STAGES = ['after_purchase', 'milestone', 'course_end'];

    protected $guarded = ['id', 'stage', 'activated_at'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'send_email' => 'boolean',
            'price_eur' => 'integer',
            'delay_hours' => 'integer',
            'available_hours' => 'integer',
            'modules_required' => 'integer',
            'activated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // When it went live: e-mails only go to triggers from around then on.
        static::saving(function (Offer $offer) {
            if ($offer->is_active && ($offer->isDirty('is_active') || $offer->activated_at === null)) {
                $offer->activated_at = now();
            }
        });
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** Switched on, written in Spanish at least, and leading somewhere. */
    public function isLive(): bool
    {
        return $this->is_active
            && filled($this->title_es)
            && ($this->course_id !== null || filled($this->url));
    }

    public function localizedTitle(): ?string
    {
        return $this->localizedValue('title');
    }

    public function localizedBody(): ?string
    {
        return $this->localizedValue('body');
    }

    public function localizedCtaLabel(): ?string
    {
        return $this->localizedValue('cta_label');
    }

    /** When a student whose trigger fell at $triggeredAt starts seeing it. */
    public function opensAt(CarbonInterface $triggeredAt): CarbonInterface
    {
        return $triggeredAt->copy()->addHours($this->delay_hours);
    }

    /** When it stops showing: null keeps it until the student closes it. */
    public function closesAt(CarbonInterface $triggeredAt): ?CarbonInterface
    {
        return $this->available_hours === null
            ? null
            : $this->opensAt($triggeredAt)->addHours($this->available_hours);
    }
}
