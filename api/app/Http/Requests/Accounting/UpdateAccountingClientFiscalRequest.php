<?php

namespace App\Http\Requests\Accounting;

use App\Support\BrazilDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountingClientFiscalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cpf_cnpj' => ['nullable', 'string', 'max:18', 'regex:' . BrazilDocument::CPF_OR_CNPJ_INPUT_PATTERN],
            'ie' => ['nullable', 'string', 'max:30'],
            'ie_indicator' => ['nullable', Rule::in(['contribuinte', 'isento', 'nao_contribuinte'])],
        ];
    }
}
