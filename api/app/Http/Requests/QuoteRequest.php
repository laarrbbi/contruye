<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteRequest extends FormRequest
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
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'scope' => ['sometimes', Rule::in(['full', 'modules'])],
            'module_ids' => ['required_if:scope,modules', 'array', 'min:1'],
            'module_ids.*' => ['integer', 'distinct', 'exists:modules,id'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function scopeValue(): string
    {
        return $this->validated('scope', 'full');
    }

    /**
     * @return int[]
     */
    public function moduleIds(): array
    {
        return array_map('intval', $this->validated('module_ids', []));
    }
}
