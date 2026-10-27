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
            'title_fr' => ['required', 'string', 'max:255'],
            'title_ar' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:courses,slug'],
            'description_fr' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'price_dzd' => ['required', 'integer', 'min:0'],
            'price_eur' => ['nullable', 'integer', 'min:0'],
            'module_discount_tiers' => ['sometimes', 'nullable', 'array', 'max:8'],
            'module_discount_tiers.*.min_modules' => ['required', 'integer', 'min:2', 'distinct'],
            'module_discount_tiers.*.percent_off' => ['required', 'integer', 'between:1,99'],
            'status' => ['sometimes', 'required', 'in:draft,published'],
            'cover_image' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
