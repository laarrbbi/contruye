<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's saved inputs for the financial template, keyed by sheet and
 * cell address. The result is always recalculated, never stored.
 */
class FinancialScenario extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'inputs',
    ];

    protected function casts(): array
    {
        return [
            'inputs' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
