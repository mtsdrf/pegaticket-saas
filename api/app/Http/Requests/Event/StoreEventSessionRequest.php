<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'gate_opens_at' => ['nullable', 'date'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', Rule::in(['rascunho', 'agendado', 'vendas_abertas', 'vendas_encerradas', 'realizado', 'cancelado'])],
            'sales_start_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date', 'after_or_equal:sales_start_at'],
        ];
    }
}
