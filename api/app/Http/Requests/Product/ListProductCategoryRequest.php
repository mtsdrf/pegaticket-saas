<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class ListProductCategoryRequest extends FormRequest
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
            'sort_by' => ['nullable', 'string', 'in:name,priority,is_active'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],

            // Busca global (OR entre colunas buscáveis) — independente dos
            // filtros por coluna abaixo, que continuam sendo AND entre si.
            'q' => ['nullable', 'string', 'max:255'],

            'name' => ['nullable', 'string', 'max:255'],
            'priority_min' => ['nullable', 'integer'],
            'priority_max' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
