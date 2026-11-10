<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A province's entry in Anexo A: its section as written, and the same rows
 * phase by phase (`data.phases["1"…"12"]`, each row [name, purpose]).
 * `data_<locale>` is the same data with the purposes and the facts
 * translated (CourseTranslationSeeder); empty, the Spanish is read.
 */
class ProvinceGuide extends Model
{
    protected $fillable = [
        'province_id',
        'ccaa',
        'section_es',
        'data',
        'data_en',
        'data_fr',
        'data_ar',
        'data_ru',
        'data_uk',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'data_en' => 'array',
            'data_fr' => 'array',
            'data_ar' => 'array',
            'data_ru' => 'array',
            'data_uk' => 'array',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * The data in the reader's language when it is translated, else the
     * Spanish, with the language it is in.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    public function localizedData(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $translated = $locale !== config('locales.default') && in_array($locale, config('locales.supported'), true)
            ? $this->{"data_{$locale}"}
            : null;

        return $translated ? [$translated, $locale] : [$this->data, (string) config('locales.default')];
    }

    /**
     * The rows of one phase: [[partner or institution, what for], …], from
     * the given data (the Spanish by default).
     *
     * @param  array<string, mixed>|null  $data
     * @return list<array{0: string, 1: string}>
     */
    public function phaseRows(int $phase, ?array $data = null): array
    {
        return ($data ?? $this->data)['phases'][(string) $phase] ?? [];
    }
}
