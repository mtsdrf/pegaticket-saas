<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class ListProductRequest extends FormRequest
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
            'sort_by' => ['nullable', 'string', 'in:name,product_type_name,product_category_name,price,is_available'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],

            // Busca global (OR entre colunas buscáveis) — independente dos
            // filtros por coluna abaixo, que continuam sendo AND entre si.
            'q' => ['nullable', 'string', 'max:255'],

            'name' => ['nullable', 'string', 'max:255'],
            // Busca exata por código de barras para leitura por scanner.
            'barcode' => ['nullable', 'string', 'max:255'],
            'product_type_uuid' => ['nullable', 'uuid'],
            'product_category_uuid' => ['nullable', 'uuid'],
            'product_type_name' => ['nullable', 'string', 'max:255'],
            'product_category_name' => ['nullable', 'string', 'max:255'],
            'price_min' => ['nullable', 'numeric'],
            'price_max' => ['nullable', 'numeric'],
            'is_available' => ['nullable', 'boolean'],
        ];
    }
}
