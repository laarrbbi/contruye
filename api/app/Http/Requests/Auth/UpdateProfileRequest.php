<?php

namespace App\Http\Requests\Auth;

use App\Support\FiscalId;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What a student edits about themselves on their account page: their name and
 * phone (the same rules as at sign-up), their province and language, and the
 * details they want on their invoices. Every field is optional, so the partner
 * filters can keep sending the province alone.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'regex:'.RegisterRequest::PHONE_PATTERN],
            'province_id' => ['sometimes', 'nullable', 'integer', 'exists:provinces,id'],
            'locale' => ['sometimes', 'string', Rule::in(config('locales.supported'))],
            'billing_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_nif' => ['sometimes', 'nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail) {
                if (is_string($value) && ! FiscalId::isValid($value)) {
                    $fail(__('This fiscal id is not valid. Check the number and its letter.'));
                }
            }],
            'billing_address' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->phone)) {
            $merge['phone'] = RegisterRequest::normalisePhone($this->phone);
        }

        foreach (['name', 'billing_name', 'billing_address'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $merge[$field] = $value === '' && $field !== 'name' ? null : $value;
            }
        }

        if (is_string($this->billing_nif)) {
            $nif = FiscalId::normalise($this->billing_nif);
            $merge['billing_nif'] = $nif === '' ? null : $nif;
        }

        $this->merge($merge);
    }
}
