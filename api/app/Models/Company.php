<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * A company of the directory: a builder, an architect, a bank… imported from
 * a company list. Its category (from its activity code) says which modules
 * it serves; the admin decides whether students see it (is_visible).
 */
class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'source', 'source_ref', 'name', 'category', 'activity_code', 'legal_form', 'address', 'postal_code',
        'city', 'province_id', 'phone', 'email', 'website', 'tax_id', 'employees', 'is_visible',
    ];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'employees' => 'integer'];
    }

    private const COUNTS_KEY = 'directory.visible-counts';

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetCounts());
        static::deleted(fn () => self::forgetCounts());
    }

    /**
     * How many companies students see in each category. The player asks on
     * every load (one count per module), so it is kept, and dropped whenever
     * a company changes: here, and after the bulk writes that skip model
     * events (the admin's filter-wide switch, the import).
     *
     * @return array<string, int>
     */
    public static function visibleCounts(): array
    {
        return Cache::remember(self::COUNTS_KEY, now()->addHour(), fn () => self::query()->visible()
            ->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category')
            ->map(fn ($n) => (int) $n)->all());
    }

    /** How many companies students see in a phase's Partners section. */
    public static function visibleCountForPhase(int $phase): int
    {
        return (int) array_sum(array_intersect_key(self::visibleCounts(), array_flip(self::categoriesForPhase($phase))));
    }

    public static function forgetCounts(): void
    {
        Cache::forget(self::COUNTS_KEY);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** The category an activity code falls in: the first matching prefix, else `otros`. */
    public static function categoryFor(?string $code): string
    {
        $code = preg_replace('/\D/', '', (string) $code);
        if ($code !== '') {
            foreach (config('directory.categories') as $key => $category) {
                foreach ($category['codes'] as $prefix) {
                    if (str_starts_with($code, $prefix)) {
                        return $key;
                    }
                }
            }
        }

        return 'otros';
    }

    /** @return list<string> the categories that serve a phase (Module.order). */
    public static function categoriesForPhase(int $phase): array
    {
        return array_keys(array_filter(
            config('directory.categories'),
            fn (array $category) => in_array($phase, $category['modules'], true),
        ));
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    /** Name, city or tax id containing the words of $q. */
    public function scopeSearch(Builder $query, ?string $q): Builder
    {
        foreach (preg_split('/\s+/', trim((string) $q), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $query->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('city', 'like', $like)->orWhere('tax_id', 'like', $like));
        }

        return $query;
    }
}
