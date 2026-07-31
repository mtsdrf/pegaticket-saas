<?php

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;

class ListEnderecoRequest extends FormRequest
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
            'sort_by' => ['nullable', 'string', 'in:logradouro,cep,cidade_name,bairro_name,is_active'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],

            'logradouro' => ['nullable', 'string', 'max:255'],
            'cep' => ['nullable', 'string', 'max:20'],
            'cidade_name' => ['nullable', 'string', 'max:255'],
            'bairro_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
