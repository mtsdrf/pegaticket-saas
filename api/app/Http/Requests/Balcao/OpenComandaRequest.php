<?php

namespace App\Http\Requests\Balcao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenComandaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('tables', 'uuid')
                    ->where(fn($q) => $q->where('tenant_id', app('tenant_id'))->whereNull('deleted_at')),
            ],
            'label' => ['nullable', 'string', 'max:255'],
            'client_comanda_uuid' => ['nullable', 'uuid'],
        ];
    }
}
