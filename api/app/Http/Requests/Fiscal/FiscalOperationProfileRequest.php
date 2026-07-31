<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiscalOperationProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'operation_nature' => ['required', Rule::in(['sale', 'return', 'transfer', 'service'])],
            'document_type' => ['required', Rule::in(['nfce', 'nfe', 'nfse'])],
            'default_cfop' => ['nullable', 'string', 'max:10'],
            'scope' => ['nullable', 'array'],
            'scope.order_origin' => ['sometimes', 'array'],
            'scope.order_origin.*' => [Rule::in(['staff', 'storefront', 'pdv', 'counter'])],
            'scope.fulfillment_type' => ['sometimes', 'array'],
            'scope.fulfillment_type.*' => [Rule::in(['delivery', 'pickup'])],
            'scope.destination_type' => ['sometimes', 'array'],
            'scope.destination_type.*' => [Rule::in(['consumer_final', 'business'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
