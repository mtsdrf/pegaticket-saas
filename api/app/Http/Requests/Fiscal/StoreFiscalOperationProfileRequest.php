<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFiscalOperationProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'operation_nature' => ['required', 'string', 'max:40'],
            'document_type' => ['required', Rule::in(['nfce', 'nfe', 'nfse'])],
            'default_cfop' => ['nullable', 'string', 'max:10'],
            'scope' => ['nullable', 'array'],
            'scope.order_origin' => ['nullable', 'array'],
            'scope.order_origin.*' => ['string', 'max:20'],
            'scope.fulfillment_type' => ['nullable', 'array'],
            'scope.fulfillment_type.*' => ['string', 'max:20'],
            'scope.destination_type' => ['nullable', 'array'],
            'scope.destination_type.*' => ['string', 'max:30'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
