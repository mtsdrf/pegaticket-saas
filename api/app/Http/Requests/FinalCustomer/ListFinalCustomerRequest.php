<?php

namespace App\Http\Requests\FinalCustomer;

use Illuminate\Foundation\Http\FormRequest;

class ListFinalCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],

            // Busca global (OR entre nome/email/telefone/CPF-CNPJ), mesmo
            // espírito do `q` em ListProductTypeRequest.
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
