<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tax_type' => ['required', 'string', Rule::in(['icms', 'icms_st', 'ipi', 'pis', 'cofins', 'iss'])],
            // scope é aberto (json) de propósito — só validamos a forma
            // geral (objeto com arrays de string). Ver migration para o
            // shape esperado. Null = regra coringa (vale pra todo tax_type).
            'scope' => ['nullable', 'array'],
            'scope.cfop' => ['sometimes', 'array'],
            'scope.cfop.*' => ['string'],
            'scope.ncm' => ['sometimes', 'array'],
            'scope.ncm.*' => ['string'],
            'scope.uf_origin' => ['sometimes', 'array'],
            'scope.uf_origin.*' => ['string', 'size:2'],
            'scope.uf_dest' => ['sometimes', 'array'],
            'scope.uf_dest.*' => ['string', 'size:2'],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'valid_from' => ['nullable', 'date'],
            // valid_to nunca pode ser anterior a valid_from — vigência
            // incoerente é rejeitada aqui (não no Service).
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'valid_to.after_or_equal' => __('messages.tax_rule.invalid_validity_range'),
        ];
    }
}
