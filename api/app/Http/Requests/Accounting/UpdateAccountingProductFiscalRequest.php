<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountingProductFiscalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ncm' => ['nullable', 'string', 'digits:8'],
            'cest' => ['nullable', 'string', 'max:10'],
            'origin' => ['nullable', 'string', 'regex:/^[0-8]$/'],
            'default_cfop' => ['nullable', 'string', 'max:10'],
            'csosn_cst' => ['nullable', 'string', 'max:10'],
        ];
    }
}
