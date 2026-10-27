<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by lesson create and update; ordering is managed by the API.
 */
class LessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Duration is optional in the form, which sends null when the box is left
     * blank. The column is NOT NULL DEFAULT 0, so null becomes 0 here rather
     * than reaching the database — and a lesson already stored at 0 can be
     * edited again, which it could not before.
     */
    protected function prepareForValidation(): void
    {
        if ($this->exists('duration_seconds') && $this->input('duration_seconds') === null) {
            $this->merge(['duration_seconds' => 0]);
        }
    }

    public function rules(): array
    {
        return [
            'title_fr' => ['required', 'string', 'max:255'],
            // French-first by design: the column is nullable and
            // localizedTitle() falls back to French until a translation lands.
            'title_ar' => ['nullable', 'string', 'max:255'],
            'bunny_video_id' => ['nullable', 'string', 'max:255'],
            'content_fr' => ['nullable', 'string', 'max:50000'],
            'content_ar' => ['nullable', 'string', 'max:50000'],
            'duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_free_preview' => ['sometimes', 'boolean'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['array'],
            'attachments.*.name' => ['required', 'string', 'max:255'],
            'attachments.*.path' => ['required', 'string', 'max:2048'],
        ];
    }
}
