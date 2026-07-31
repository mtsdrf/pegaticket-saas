<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tax_type' => ['required', Rule::in(['icms', 'icms_st', 'ipi', 'pis', 'cofins', 'iss'])],
            'rate_percent' => ['required', 'numeric', 'min:0'],
            'scope' => ['nullable', 'array'],
            'scope.cfop' => ['nullable', 'array'],
            'scope.cfop.*' => ['string', 'max:10'],
            'scope.ncm' => ['nullable', 'array'],
            'scope.ncm.*' => ['string', 'max:10'],
            'scope.uf_origin' => ['nullable', 'array'],
            'scope.uf_origin.*' => ['string', 'size:2'],
            'scope.uf_dest' => ['nullable', 'array'],
            'scope.uf_dest.*' => ['string', 'size:2'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
