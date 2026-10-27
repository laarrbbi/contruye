<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    use HasFactory, HasLocalizedAttributes;

    protected $fillable = [
        'course_id',
        'order',
        'title_fr',
        'title_ar',
        'description_fr',
        'description_ar',
        'price_dzd',
        'price_eur',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'price_dzd' => 'integer',
            'price_eur' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('order');
    }

    public function contentBlocks(): HasMany
    {
        return $this->hasMany(ContentBlock::class)->orderBy('order');
    }

    public function localizedDescription(?string $locale = null): ?string
    {
        return $this->localizedValue('description', $locale);
    }

    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(Partner::class)
            ->withPivot(['order', 'note_fr', 'note_ar'])
            ->withTimestamps()
            ->orderBy('module_partner.order');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }

    public function localizedTitle(?string $locale = null): string
    {
        return (string) $this->localizedValue('title', $locale);
    }
}
