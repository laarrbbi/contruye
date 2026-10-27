<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The ids must be exactly the set of the course's modules: `size`
     * catches omissions, `distinct` catches duplicates and the `exists`
     * clause catches ids belonging to another course.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $course = $this->route('course');

        return [
            'module_ids' => ['required', 'array', 'size:'.$course->modules()->count()],
            'module_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('modules', 'id')->where('course_id', $course->id),
            ],
        ];
    }
}
