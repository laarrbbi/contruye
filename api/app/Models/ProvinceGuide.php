<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A province's entry in Anexo A: its section as written, and the same rows
 * phase by phase (`data.phases["1"…"12"]`, each row [name, purpose]).
 */
class ProvinceGuide extends Model
{
    protected $fillable = [
        'province_id',
        'ccaa',
        'section_es',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * The rows of one phase: [[partner or institution, what for], …].
     *
     * @return list<array{0: string, 1: string}>
     */
    public function phaseRows(int $phase): array
    {
        return $this->data['phases'][(string) $phase] ?? [];
    }
}
