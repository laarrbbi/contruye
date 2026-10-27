<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A vetted service provider recommended alongside a module — the people a
 * promoter actually needs at that step of the circuit (notary, tax adviser,
 * bank, architects, insurer, builder…).
 */
class Partner extends Model
{
    use HasFactory, HasLocalizedAttributes;

    /** Service categories along the promotion circuit. */
    public const CATEGORIES = [
        'notario',
        'asesor_fiscal',
        'banco',
        'arquitectura',
        'aseguradora',
        'constructora',
        'abogado',
        'marketing',
        'otro',
    ];

    protected $fillable = [
        'name',
        'category',
        'description_es',
        'description_en',
        'website',
        'phone',
        'email',
        'logo_path',
        'is_active',
        'is_national',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_national' => 'boolean',
        ];
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class)
            ->withPivot(['order', 'note_es', 'note_en'])
            ->withTimestamps();
    }

    /**
     * The provinces this partner actually operates in. Empty for a national
     * partner, which covers them all — always read coverage through
     * {@see coversProvince()} rather than this relation alone.
     */
    public function provinces(): BelongsToMany
    {
        return $this->belongsToMany(Province::class)->orderBy('code');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Partners operating in a province: those explicitly covering it, plus every
     * national one.
     */
    public function scopeInProvince(Builder $query, int $provinceId): Builder
    {
        return $query->where(fn (Builder $inner) => $inner
            ->where('is_national', true)
            ->orWhereHas('provinces', fn (Builder $w) => $w->where('provinces.id', $provinceId)));
    }

    public function coversProvince(int $provinceId): bool
    {
        return $this->is_national || $this->provinces->contains('id', $provinceId);
    }

    public function localizedDescription(?string $locale = null): ?string
    {
        return $this->localizedValue('description', $locale);
    }
}
