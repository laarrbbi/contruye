<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProgressHeartbeatRequest extends FormRequest
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
            // A day is far beyond any lesson; an unbounded value overflowed
            // the unsigned column and answered 500.
            'position_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
        ];
    }
}
