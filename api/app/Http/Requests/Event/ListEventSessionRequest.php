<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class ListEventSessionRequest extends FormRequest
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
            'sort_by' => ['nullable', 'string', 'in:starts_at,status'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
            'status' => ['nullable', 'string', 'in:rascunho,agendado,vendas_abertas,vendas_encerradas,realizado,cancelado'],
        ];
    }
}
