<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReactivationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'days_without_order' => ['required', 'integer', 'min:1'],
            'coupon_type' => ['required', 'string', Rule::in(['percentage', 'fixed'])],
            'coupon_value' => [
                'required',
                'numeric',
                'min:0.01',
                Rule::when($this->input('coupon_type') === 'percentage', ['max:100']),
            ],
            'coupon_validity_days' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
