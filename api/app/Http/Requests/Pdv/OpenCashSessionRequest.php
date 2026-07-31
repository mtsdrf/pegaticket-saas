<?php

namespace App\Http\Requests\Pdv;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Opcional: sem ele, o service resolve/cria o caixa default do
            // tenant. Quando informado, precisa existir e ser do tenant ativo.
            'cash_register_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('cash_registers', 'uuid')
                    ->where(fn($q) => $q->where('tenant_id', app('tenant_id'))->whereNull('deleted_at')),
            ],
            'opening_amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
