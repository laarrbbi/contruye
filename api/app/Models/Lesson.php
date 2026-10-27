<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasFactory, HasLocalizedAttributes;

    protected $fillable = [
        'module_id',
        'order',
        'title_fr',
        'title_ar',
        'bunny_video_id',
        'duration_seconds',
        'is_free_preview',
        'content_fr',
        'content_ar',
        'key_points_fr',
        'key_points_ar',
        'is_reflex_sheet',
        'attachments',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'duration_seconds' => 'integer',
            'is_free_preview' => 'boolean',
            'attachments' => 'array',
            'key_points_fr' => 'array',
            'key_points_ar' => 'array',
            'is_reflex_sheet' => 'boolean',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function contentBlocks(): HasMany
    {
        return $this->hasMany(ContentBlock::class)->orderBy('order');
    }

    /** @return list<string> */
    public function localizedKeyPoints(?string $locale = null): array
    {
        return array_values($this->localizedArray('key_points', $locale));
    }

    public function localizedContent(?string $locale = null): ?string
    {
        return $this->localizedValue('content', $locale);
    }

    public function localizedTitle(?string $locale = null): string
    {
        return (string) $this->localizedValue('title', $locale);
    }
}
