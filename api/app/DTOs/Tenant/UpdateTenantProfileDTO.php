<?php

namespace App\DTOs\Tenant;

use App\Support\BrazilDocument;
use Illuminate\Http\UploadedFile;

class UpdateTenantProfileDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?UploadedFile $logo,
        public readonly ?UploadedFile $fiscalCertificateA1,
        // Cadastro fiscal do emitente (roadmap Fiscal D0). Cada campo carrega
        // um flag "presente no request" para distinguir "não enviado"
        // (mantém) de "enviado vazio" (limpa) no Service.
        public readonly array $fiscal = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        $fiscalKeys = [
            'cnpj',
            'ie',
            'im',
            'cnae',
            'tax_regime',
            'fiscal_environment',
            'ibge_city_code',
            'fiscal_provider',
            'fiscal_nfe_series',
            'fiscal_nfce_series',
            'fiscal_nfse_series',
            'fiscal_next_nfe_number',
            'fiscal_next_nfce_number',
            'fiscal_next_nfse_number',
            'fiscal_nfce_csc_id',
            'fiscal_nfce_csc_code',
            'fiscal_provider_api_token',
            'fiscal_certificate_a1_password',
        ];
        $fiscal = [];
        foreach ($fiscalKeys as $key) {
            if (array_key_exists($key, $data)) {
                if ($data[$key] === '') {
                    $fiscal[$key] = null;
                    continue;
                }

                $fiscal[$key] = match ($key) {
                    'cnpj' => BrazilDocument::normalizeCnpj($data[$key]),
                    default => $data[$key],
                };
            }
        }

        if (!empty($data['clear_fiscal_nfce_csc_code'])) {
            $fiscal['fiscal_nfce_csc_code'] = null;
        }

        if (!empty($data['clear_fiscal_provider_api_token'])) {
            $fiscal['fiscal_provider_api_token'] = null;
        }

        if (!empty($data['clear_fiscal_certificate_a1'])) {
            $fiscal['fiscal_certificate_a1_data'] = null;
            $fiscal['fiscal_certificate_a1_name'] = null;
            $fiscal['fiscal_certificate_a1_mime'] = null;
            $fiscal['fiscal_certificate_a1_password'] = null;
            $fiscal['fiscal_certificate_a1_updated_at'] = null;
        }

        return new self(
            name: $data['name'],
            logo: $data['logo'] ?? null,
            fiscalCertificateA1: $data['fiscal_certificate_a1'] ?? null,
            fiscal: $fiscal,
        );
    }
}
