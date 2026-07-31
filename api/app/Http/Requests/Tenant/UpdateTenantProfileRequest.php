<?php

namespace App\Http\Requests\Tenant;

use App\Support\BrazilDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizeBooleanFlags([
            'clear_fiscal_nfce_csc_code',
            'clear_fiscal_provider_api_token',
            'clear_fiscal_certificate_a1',
        ]));
    }

    public function rules(): array
    {
        // Fronteira de segurança: o dono da empresa edita nome, logo e o
        // próprio cadastro fiscal (roadmap Fiscal D0). slug/plan/status NÃO
        // são aceitos aqui de propósito. Campos fiscais são todos nullable —
        // não obrigam quem só quer atualizar o nome/logo.
        return [
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'cnpj' => ['nullable', 'string', 'max:18', 'regex:' . BrazilDocument::CNPJ_INPUT_PATTERN],
            'ie' => ['nullable', 'string', 'max:30'], // "ISENTO" é válido
            'im' => ['nullable', 'string', 'max:30'],
            'cnae' => ['nullable', 'string', 'max:20'],
            'tax_regime' => ['nullable', Rule::in(['simples_nacional', 'lucro_presumido', 'lucro_real'])],
            'fiscal_environment' => ['nullable', Rule::in(['homologacao', 'producao'])],
            'ibge_city_code' => ['nullable', 'string', 'max:7'],
            'fiscal_provider' => ['nullable', Rule::in(['manual', 'focus_nfe', 'plugnotas', 'nfeio', 'sped_nfe'])],
            'fiscal_nfe_series' => ['nullable', 'string', 'max:20'],
            'fiscal_nfce_series' => ['nullable', 'string', 'max:20'],
            'fiscal_nfse_series' => ['nullable', 'string', 'max:20'],
            'fiscal_next_nfe_number' => ['nullable', 'integer', 'min:1', 'max:999999999'],
            'fiscal_next_nfce_number' => ['nullable', 'integer', 'min:1', 'max:999999999'],
            'fiscal_next_nfse_number' => ['nullable', 'integer', 'min:1', 'max:999999999'],
            'fiscal_nfce_csc_id' => ['nullable', 'string', 'max:40'],
            'fiscal_nfce_csc_code' => ['nullable', 'string', 'max:255'],
            'fiscal_provider_api_token' => ['nullable', 'string', 'max:5000'],
            'fiscal_certificate_a1' => ['nullable', 'file', 'mimes:pfx,p12', 'max:5120'],
            'fiscal_certificate_a1_password' => ['nullable', 'string', 'max:255'],
            'clear_fiscal_nfce_csc_code' => ['nullable', 'boolean'],
            'clear_fiscal_provider_api_token' => ['nullable', 'boolean'],
            'clear_fiscal_certificate_a1' => ['nullable', 'boolean'],
        ];
    }

    /**
     * FormData sempre chega como string no Laravel. Esses flags de limpeza
     * podem vir como "true"/"false", "1"/"0" ou vazios; normalizamos aqui
     * antes da validação para manter o contrato estável em JSON e multipart.
     *
     * @param list<string> $keys
     * @return array<string, bool|null>
     */
    private function normalizeBooleanFlags(array $keys): array
    {
        $normalized = [];

        foreach ($keys as $key) {
            if (!$this->exists($key)) {
                continue;
            }

            $value = $this->input($key);

            if ($value === null || $value === '') {
                $normalized[$key] = null;
                continue;
            }

            $boolean = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($boolean !== null) {
                $normalized[$key] = $boolean;
            }
        }

        return $normalized;
    }
}
