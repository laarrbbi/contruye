<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Course extends Model
{
    use HasFactory, HasLocalizedAttributes;

    protected $fillable = [
        'slug',
        'title_es',
        'title_en',
        'description_es',
        'description_en',
        'price_eur',
        'module_discount_tiers',
        'status',
        'cover_image',
        'circuit_map_path',
    ];

    protected function casts(): array
    {
        return [
            'price_eur' => 'integer',
            'module_discount_tiers' => 'array',
        ];
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('order');
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CourseDocument::class)->orderBy('order');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * The promotion currently running on this course, if any. When several
     * overlap, the best discount for the student wins.
     */
    public function activeCampaign(): ?Campaign
    {
        // Best discount wins; ties broken deterministically (soonest deadline,
        // then id) so the countdown a student sees never changes between
        // requests just because two campaigns overlap.
        //
        // Resolved in memory when `campaigns` is already loaded: the catalog
        // eager-loads them, and re-querying per course was an N+1.
        if ($this->relationLoaded('campaigns')) {
            return $this->campaigns
                ->filter(fn (Campaign $campaign) => $campaign->isRunning())
                ->sortBy([['percent_off', 'desc'], ['ends_at', 'asc'], ['id', 'asc']])
                ->first();
        }

        return $this->campaigns()
            ->active()
            ->orderByDesc('percent_off')
            ->orderBy('ends_at')
            ->orderBy('id')
            ->first();
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function localizedTitle(?string $locale = null): string
    {
        return (string) $this->localizedValue('title', $locale);
    }

    public function localizedDescription(?string $locale = null): ?string
    {
        return $this->localizedValue('description', $locale);
    }
}
