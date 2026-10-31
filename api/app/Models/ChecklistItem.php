<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A curated action step at the end of a module — distilled from the module's
 * own content, never invented. Students tick items off as they execute the
 * step on their own project.
 */
class ChecklistItem extends Model
{
    use HasFactory, HasLocalizedAttributes;

    protected $fillable = [
        'module_id',
        'order',
        'text_es',
        'text_en',

        'text_fr',

        'text_ar',

        'text_ru',

        'text_uk',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /** Students who checked this step. */
    public function checkedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'checklist_item_user')->withTimestamps();
    }

    /** Falls back to Spanish while the English translation is pending. */
    public function localizedText(?string $locale = null): string
    {
        return (string) $this->localizedValue('text', $locale);
    }
}
