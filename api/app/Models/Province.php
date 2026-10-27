<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One of the 52 Spanish provinces (Ceuta and Melilla included). Reference
 * data: rows are seeded from the INE list and never edited from the admin.
 */
class Province extends Model
{
    use HasLocalizedAttributes;

    public $timestamps = false;

    protected $fillable = ['code', 'name_es', 'name_en'];

    protected function casts(): array
    {
        return [
            'code' => 'integer',
        ];
    }

    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(Partner::class);
    }

    public function localizedName(?string $locale = null): string
    {
        return (string) $this->localizedValue('name', $locale);
    }
}
