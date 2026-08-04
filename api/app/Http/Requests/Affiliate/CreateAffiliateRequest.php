<?php

namespace App\Http\Requests\Affiliate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAffiliateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:180'],
            'tracking_code' => [
                'required',
                'string',
                'max:40',
                'alpha_dash',
                Rule::unique('affiliates', 'tracking_code')
                    ->where('tenant_id', app('tenant_id'))
                    ->whereNull('deleted_at'),
            ],
            'commission_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
