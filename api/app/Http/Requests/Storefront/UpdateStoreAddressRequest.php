<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Endereço da empresa (loja pública) — reaproveita EXATAMENTE as mesmas
 * regras de StoreEnderecoRequest (CRUD normal de Endereco). PUT em lote:
 * substitui/cria o endereço inteiro, por isso os campos da cadeia geográfica
 * são obrigatórios (não é update parcial).
 */
class UpdateStoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logradouro' => ['required', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'cep' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'estado_uuid' => [
                'required',
                'uuid',
                Rule::exists('estados', 'uuid')->whereNull('deleted_at'),
            ],
            'cidade_uuid' => [
                'required',
                'uuid',
                Rule::exists('cidades', 'uuid')->whereNull('deleted_at'),
            ],
            'bairro_uuid' => [
                'required',
                'uuid',
                Rule::exists('bairros', 'uuid')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'estado_uuid.exists' => __('messages.endereco.invalid_estado'),
            'cidade_uuid.exists' => __('messages.endereco.invalid_cidade'),
            'bairro_uuid.exists' => __('messages.endereco.invalid_bairro'),
        ];
    }
}
