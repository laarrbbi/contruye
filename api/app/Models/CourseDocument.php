<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A course-level text from the instructor's package: the introduction, the
 * resources list, the annexes. Markdown, kept as delivered.
 */
class CourseDocument extends Model
{
    use HasLocalizedAttributes;

    public const ACCESS = ['enrolled', 'full'];

    protected $fillable = [
        'course_id',
        'slug',
        'order',
        'title_es',
        'title_en',
        'body_es',
        'body_en',
        'access',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function localizedTitle(?string $locale = null): string
    {
        return (string) $this->localizedValue('title', $locale);
    }

    public function localizedBody(?string $locale = null): string
    {
        return (string) $this->localizedValue('body', $locale);
    }
}
