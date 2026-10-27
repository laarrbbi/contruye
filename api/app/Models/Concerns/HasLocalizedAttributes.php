<?php

namespace App\Models\Concerns;

/**
 * Resolves a `<base>_<locale>` attribute through a fallback chain.
 *
 * Course material is authored in French and translated per locale into
 * suffixed columns (`title_fr`, `title_ar`…). A locale with no column of its
 * own — or with an empty one because the translation is still pending — reads
 * the authored French rather than rendering a blank.
 *
 * Keeping the chain here means adding an `_en` column later is a migration
 * plus an admin field: not one call site changes.
 */
trait HasLocalizedAttributes
{
    protected function localizedValue(string $base, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $default = config('locales.default', 'fr');

        foreach ([$locale, $default] as $candidate) {
            $column = "{$base}_{$candidate}";

            // A locale may simply have no column for this field yet.
            if (! array_key_exists($column, $this->attributes)) {
                continue;
            }

            $value = $this->{$column};

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Same chain, for a column cast to an array (key points, tags…).
     *
     * @return array<int, mixed>
     */
    protected function localizedArray(string $base, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $default = config('locales.default', 'fr');

        foreach ([$locale, $default] as $candidate) {
            $column = "{$base}_{$candidate}";

            if (! array_key_exists($column, $this->attributes)) {
                continue;
            }

            $value = $this->{$column};

            if (is_array($value) && $value !== []) {
                return $value;
            }
        }

        return [];
    }
}
