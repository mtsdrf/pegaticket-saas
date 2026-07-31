<?php

namespace App\Http\Requests\Balcao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CloseComandaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'apply_service_fee' => ['nullable', 'boolean'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', Rule::in(['cash', 'pix', 'card', 'debit', 'credit'])],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
