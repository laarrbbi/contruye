<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
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
            'title_en' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:courses,slug'],
            'description_es' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'price_eur' => ['required', 'integer', 'min:0'],
            'module_discount_tiers' => ['sometimes', 'nullable', 'array', 'max:8'],
            'module_discount_tiers.*.min_modules' => ['required', 'integer', 'min:2', 'distinct'],
            'module_discount_tiers.*.percent_off' => ['required', 'integer', 'between:1,99'],
            'status' => ['sometimes', 'required', 'in:draft,published'],
            'cover_image' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
