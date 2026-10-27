<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One of the 58 official Algerian wilayas. Reference data: rows are seeded
 * from the official découpage and never edited from the admin.
 */
class Wilaya extends Model
{
    use HasLocalizedAttributes;

    public $timestamps = false;

    protected $fillable = ['code', 'name_fr', 'name_ar'];

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
