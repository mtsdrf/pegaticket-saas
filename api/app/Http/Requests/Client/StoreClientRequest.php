<?php

namespace App\Http\Requests\Client;

use App\Support\BrazilDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:90'],
            // Obrigatório na criação (decisão de produto 2026-07-24): sem
            // CPF/CNPJ, pagamentos Pix com identificação do pagador tendem a
            // ser rejeitados pelo PSP. Não retroage sobre clientes antigos
            // (UpdateClientRequest continua nullable) e não bloqueia a geração
            // de Pix em si.
            'cpf_cnpj' => ['required', 'string', 'max:18', 'regex:' . BrazilDocument::CPF_OR_CNPJ_INPUT_PATTERN],
            'ie' => ['nullable', 'string', 'max:30'],
            'ie_indicator' => ['nullable', Rule::in(['contribuinte', 'isento', 'nao_contribuinte'])],
            'phone_primary' => ['nullable', 'string', 'max:30'],
            'phone_secondary' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'is_trusted' => ['boolean'],
            'is_active' => ['boolean'],

            // Endereço criado inline junto com o cliente (sem tela de endereço separada).
            'logradouro' => ['required', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'cep' => ['nullable', 'string', 'max:20'],
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
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'cpf_cnpj.required' => __('messages.client.cpf_cnpj_required'),
            'cpf_cnpj.regex' => __('messages.client.cpf_cnpj_invalid'),
            'estado_uuid.exists' => __('messages.endereco.invalid_estado'),
            'cidade_uuid.exists' => __('messages.endereco.invalid_cidade'),
            'bairro_uuid.exists' => __('messages.endereco.invalid_bairro'),
        ];
    }
}
