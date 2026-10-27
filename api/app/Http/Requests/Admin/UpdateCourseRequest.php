<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateCourseRequest extends StoreCourseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['slug'] = [
            'nullable',
            'string',
            'max:255',
            'alpha_dash',
            Rule::unique('courses', 'slug')->ignore($this->route('course')),
        ];

        return $rules;
    }
}
