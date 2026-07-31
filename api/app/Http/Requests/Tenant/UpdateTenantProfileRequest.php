<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Fronteira de segurança: o dono da empresa edita apenas nome e
        // logo por aqui. slug/plan/status NÃO são aceitos de propósito.
        return [
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'ie' => ['nullable', 'string', 'max:30'],
            'im' => ['nullable', 'string', 'max:30'],
            'cnae' => ['nullable', 'string', 'max:20'],
            'tax_regime' => ['nullable', Rule::in(['simples_nacional', 'lucro_presumido', 'lucro_real'])],
            'fiscal_environment' => ['nullable', Rule::in(['homologacao', 'producao'])],
            'ibge_city_code' => ['nullable', 'string', 'max:10'],
            'fiscal_provider' => ['nullable', Rule::in(['manual', 'focus_nfe', 'plugnotas', 'nfeio', 'sped_nfe'])],
            'fiscal_nfe_series' => ['nullable', 'string', 'max:20'],
            'fiscal_nfce_series' => ['nullable', 'string', 'max:20'],
            'fiscal_nfse_series' => ['nullable', 'string', 'max:20'],
            'fiscal_next_nfe_number' => ['nullable', 'integer', 'min:1'],
            'fiscal_next_nfce_number' => ['nullable', 'integer', 'min:1'],
            'fiscal_next_nfse_number' => ['nullable', 'integer', 'min:1'],
            'fiscal_nfce_csc_id' => ['nullable', 'string', 'max:20'],
            'fiscal_nfce_csc_code' => ['nullable', 'string', 'max:255'],
            'fiscal_provider_api_token' => ['nullable', 'string', 'max:2000'],
            'fiscal_certificate_a1' => ['nullable', 'file', 'mimes:pfx,p12', 'max:10240'],
            'fiscal_certificate_a1_password' => ['nullable', 'string', 'max:255'],
            'clear_fiscal_nfce_csc_code' => ['sometimes', 'boolean'],
            'clear_fiscal_provider_api_token' => ['sometimes', 'boolean'],
            'clear_fiscal_certificate_a1' => ['sometimes', 'boolean'],
        ];
    }
}
