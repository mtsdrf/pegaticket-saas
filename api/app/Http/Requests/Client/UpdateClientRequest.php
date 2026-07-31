<?php

namespace App\Http\Requests\Client;

use App\Support\BrazilDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:90'],
            // Cadastro fiscal do destinatário (roadmap Fiscal D0) — nullable.
            'cpf_cnpj' => ['nullable', 'string', 'max:18', 'regex:' . BrazilDocument::CPF_OR_CNPJ_INPUT_PATTERN],
            'ie' => ['nullable', 'string', 'max:30'],
            'ie_indicator' => ['nullable', Rule::in(['contribuinte', 'isento', 'nao_contribuinte'])],
            'phone_primary' => ['nullable', 'string', 'max:30'],
            'phone_secondary' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'is_trusted' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],

            // Endereço: só validado se algum campo vier no request (update parcial).
            'logradouro' => ['sometimes', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'cep' => ['nullable', 'string', 'max:20'],
            'estado_uuid' => [
                'sometimes',
                'uuid',
                Rule::exists('estados', 'uuid')->whereNull('deleted_at'),
            ],
            'cidade_uuid' => [
                'sometimes',
                'uuid',
                Rule::exists('cidades', 'uuid')->whereNull('deleted_at'),
            ],
            'bairro_uuid' => [
                'sometimes',
                'uuid',
                Rule::exists('bairros', 'uuid')->whereNull('deleted_at'),
            ],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
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
