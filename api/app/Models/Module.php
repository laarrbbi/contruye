<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Module extends Model
{
    use HasFactory, HasLocalizedAttributes;

    protected $fillable = [
        'course_id',
        'order',
        'kind',
        'title_es',
        'title_en',
        'description_es',
        'description_en',
        'price_eur',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
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
            ->withPivot(['order', 'note_es', 'note_en'])
            ->withTimestamps()
            ->orderBy('module_partner.order');
    }

    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class);
    }

    public function isFinancials(): bool
    {
        return $this->kind === 'financials';
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
