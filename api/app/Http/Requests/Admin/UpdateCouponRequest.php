<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateCouponRequest extends StoreCouponRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['code'] = [
            'required',
            'string',
            'max:64',
            'alpha_dash',
            Rule::unique('coupons', 'code')->ignore($this->route('coupon')),
        ];

        return $rules;
    }
}
