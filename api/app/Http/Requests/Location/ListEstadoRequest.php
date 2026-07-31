<?php

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;

class ListEstadoRequest extends FormRequest
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
            'sort_by' => ['nullable', 'string', 'in:name,uf,is_active'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],

            'name' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', 'string', 'max:2'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
