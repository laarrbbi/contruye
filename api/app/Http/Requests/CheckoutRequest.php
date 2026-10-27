<?php

namespace App\Http\Requests;

use App\Services\PaymentMethods;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CheckoutRequest extends FormRequest
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
            'method' => ['required', Rule::in(PaymentMethods::ALL)],
            'coupon_code' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * Refused before any order exists: an unconfigured gateway used to
     * create the order, fail to reach the provider, and leave a `failed`
     * row behind for a payment that could never have happened.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $method = $this->input('method');

                if (is_string($method) && in_array($method, PaymentMethods::ALL, true)
                    && ! PaymentMethods::isAvailable($method)) {
                    $validator->errors()->add('method', __('This payment method is not available at the moment.'));
                }
            },
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
