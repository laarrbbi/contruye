<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's moment for one of the offers: when its trigger happened (the
 * purchase, the milestone, the end of the course) and what the student did
 * with it. One row per student and stage, so each offer comes once.
 */
class UserOffer extends Model
{
    protected $fillable = ['user_id', 'stage', 'triggered_at', 'emailed_at', 'seen_at', 'clicked_at', 'dismissed_at'];

    protected function casts(): array
    {
        return [
            'triggered_at' => 'datetime',
            'emailed_at' => 'datetime',
            'seen_at' => 'datetime',
            'clicked_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'stage', 'stage');
    }
}
