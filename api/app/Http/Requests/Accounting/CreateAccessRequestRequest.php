<?php

namespace App\Http\Requests\Accounting;

use App\Support\BrazilDocument;
use Illuminate\Foundation\Http\FormRequest;

class CreateAccessRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_cnpj' => ['required', 'string', 'max:18', 'regex:' . BrazilDocument::CNPJ_INPUT_PATTERN],
        ];
    }
}
