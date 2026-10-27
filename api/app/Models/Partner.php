<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A vetted service provider recommended alongside a module — the people a
 * promoter actually needs at that step of the circuit (notary, chartered
 * accountant, bank, design office…).
 */
class Partner extends Model
{
    use HasFactory, HasLocalizedAttributes;

    /** Service categories along the promotion circuit. */
    public const CATEGORIES = [
        'notaire',
        'expert_comptable',
        'banque',
        'bureau_etudes',
        'marketing',
        'consultant_agrement',
        'autre',
    ];

    protected $fillable = [
        'name',
        'category',
        'description_fr',
        'description_ar',
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
            ->withPivot(['order', 'note_fr', 'note_ar'])
            ->withTimestamps();
    }

    /**
     * The wilayas this partner actually operates in. Empty for a national
     * partner, which covers them all — always read coverage through
     * {@see coversWilaya()} rather than this relation alone.
     */
    public function wilayas(): BelongsToMany
    {
        return $this->belongsToMany(Wilaya::class)->orderBy('code');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Partners operating in a wilaya: those explicitly covering it, plus every
     * national one.
     */
    public function scopeInWilaya(Builder $query, int $wilayaId): Builder
    {
        return $query->where(fn (Builder $inner) => $inner
            ->where('is_national', true)
            ->orWhereHas('wilayas', fn (Builder $w) => $w->where('wilayas.id', $wilayaId)));
    }

    public function coversWilaya(int $wilayaId): bool
    {
        return $this->is_national || $this->wilayas->contains('id', $wilayaId);
    }

    public function localizedDescription(?string $locale = null): ?string
    {
        return $this->localizedValue('description', $locale);
    }
}
