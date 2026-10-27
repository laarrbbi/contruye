<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by module create and update — a module is just its two titles;
 * ordering is managed by the API.
 */
class ModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title_es' => ['required', 'string', 'max:255'],
            // Spanish-first by design: the column is nullable and
            // localizedTitle() falls back to Spanish until a translation lands.
            'title_en' => ['nullable', 'string', 'max:255'],
            'price_eur' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
