<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's own to-do entry attached to a module, alongside the curated
 * checklist — add, tick, edit, delete, Google-Tasks style. Visible only to
 * its author.
 */
class CustomTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'module_id',
        'body',
        'is_done',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
