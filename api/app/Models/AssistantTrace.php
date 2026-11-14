<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What happened for one question: the route, the model, tokens, cost,
 * credits, the pieces of the course retrieved, the tools run, the guards'
 * flags and the time taken. Never the API key; the question and the answer
 * live in the student's conversation, not here.
 */
class AssistantTrace extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'chunks' => 'array',
            'tools' => 'array',
            'flags' => 'array',
            'cost_usd' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
