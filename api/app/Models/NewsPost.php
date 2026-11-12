<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use App\Support\Markdown;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A piece of news: written in the admin in Spanish (and in any other
 * language that has a translation), shown on /noticias once published and
 * its date has come.
 */
class NewsPost extends Model
{
    use HasLocalizedAttributes;

    protected $guarded = ['id', 'image_path'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** Published, dated, and the date has come. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isVisible(): bool
    {
        return $this->is_published && $this->published_at !== null && $this->published_at->lte(now());
    }

    public function localizedTitle(?string $locale = null): ?string
    {
        return $this->localizedValue('title', $locale);
    }

    public function localizedSummary(?string $locale = null): ?string
    {
        return $this->localizedValue('summary', $locale);
    }

    /** The text as written (Markdown). */
    public function localizedBody(?string $locale = null): ?string
    {
        return $this->localizedValue('body', $locale);
    }

    /** The text as HTML (raw HTML stripped, laws linked), in the language it is read in. */
    public function bodyHtml(?string $locale = null): ?string
    {
        return Markdown::toHtml($this->localizedValue('body', $locale));
    }

    public function imageUrl(): ?string
    {
        if ($this->image_path === null) {
            return null;
        }

        // The file name changes with each upload, so the address can be cached for good.
        return url("/api/news/{$this->slug}/image").'?v='.substr(md5($this->image_path), 0, 8);
    }

    /** A free address from a title: «Ley de vivienda 2026» → ley-de-vivienda-2026(-2…). */
    public static function uniqueSlug(string $title, ?int $except = null): string
    {
        $base = Str::limit(Str::slug($title), 150, '') ?: 'noticia';
        $slug = $base;
        for ($n = 2; static::query()->where('slug', $slug)->when($except, fn ($q) => $q->whereKeyNot($except))->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
