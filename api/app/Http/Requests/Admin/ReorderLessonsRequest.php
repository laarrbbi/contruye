<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderLessonsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The ids must be exactly the set of the module's lessons (see
     * ReorderModulesRequest for the rationale of each rule).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $module = $this->route('module');

        return [
            'lesson_ids' => ['required', 'array', 'size:'.$module->lessons()->count()],
            'lesson_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('lessons', 'id')->where('module_id', $module->id),
            ],
        ];
    }
}
