<?php

namespace App\Http\Requests\Accounting;

use App\Support\BrazilDocument;
use Illuminate\Foundation\Http\FormRequest;

class RegisterAccountingOfficeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cnpj' => ['required', 'string', 'max:18', 'regex:' . BrazilDocument::CNPJ_INPUT_PATTERN],
            'company_name' => ['required', 'string', 'max:255'],
            'responsible_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }
}
