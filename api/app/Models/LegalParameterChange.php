<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change to a legal parameter: who, when, which value (a parameter code
 * or a community, and `value`, `itp` or `ajd`), before and after. Written
 * once, never edited.
 */
class LegalParameterChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'subject', 'field', 'old_value', 'new_value'];

    protected function casts(): array
    {
        return [
            'old_value' => 'float',
            'new_value' => 'float',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
