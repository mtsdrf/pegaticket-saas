<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class ListClientRequest extends FormRequest
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
            'sort_by' => ['nullable', 'string', 'in:name,phone_primary,cidade_name,is_active'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],

            // Busca global (OR entre colunas buscáveis) — independente dos
            // filtros por coluna abaixo, que continuam sendo AND entre si.
            'q' => ['nullable', 'string', 'max:255'],

            'name' => ['nullable', 'string', 'max:90'],
            'phone_primary' => ['nullable', 'string', 'max:30'],
            'cidade_name' => ['nullable', 'string', 'max:255'],
            'category_uuid' => ['nullable', 'uuid'],
            'cidade_uuid' => ['nullable', 'uuid'],
            'bairro_uuid' => ['nullable', 'uuid'],
            'dia_ideal_uuid' => ['nullable', 'uuid'],
            'periodo_ideal_uuid' => ['nullable', 'uuid'],
            'is_trusted' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
