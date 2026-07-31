<?php

namespace App\DTOs\Tenant;

use Illuminate\Http\UploadedFile;

class UpdateTenantProfileDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?UploadedFile $logo,
        public readonly ?string $cnpj,
        public readonly ?string $ie,
        public readonly ?string $im,
        public readonly ?string $cnae,
        public readonly ?string $taxRegime,
        public readonly ?string $fiscalEnvironment,
        public readonly ?string $ibgeCityCode,
        public readonly ?string $fiscalProvider,
        public readonly ?string $fiscalNfeSeries,
        public readonly ?string $fiscalNfceSeries,
        public readonly ?string $fiscalNfseSeries,
        public readonly ?int $fiscalNextNfeNumber,
        public readonly ?int $fiscalNextNfceNumber,
        public readonly ?int $fiscalNextNfseNumber,
        public readonly ?string $fiscalNfceCscId,
        public readonly ?string $fiscalNfceCscCode,
        public readonly ?string $fiscalProviderApiToken,
        public readonly ?UploadedFile $fiscalCertificateA1,
        public readonly ?string $fiscalCertificateA1Password,
        public readonly bool $clearFiscalNfceCscCode,
        public readonly bool $clearFiscalProviderApiToken,
        public readonly bool $clearFiscalCertificateA1,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            logo: $data['logo'] ?? null,
            cnpj: $data['cnpj'] ?? null,
            ie: $data['ie'] ?? null,
            im: $data['im'] ?? null,
            cnae: $data['cnae'] ?? null,
            taxRegime: $data['tax_regime'] ?? null,
            fiscalEnvironment: $data['fiscal_environment'] ?? null,
            ibgeCityCode: $data['ibge_city_code'] ?? null,
            fiscalProvider: $data['fiscal_provider'] ?? null,
            fiscalNfeSeries: $data['fiscal_nfe_series'] ?? null,
            fiscalNfceSeries: $data['fiscal_nfce_series'] ?? null,
            fiscalNfseSeries: $data['fiscal_nfse_series'] ?? null,
            fiscalNextNfeNumber: array_key_exists('fiscal_next_nfe_number', $data) && $data['fiscal_next_nfe_number'] !== null ? (int) $data['fiscal_next_nfe_number'] : null,
            fiscalNextNfceNumber: array_key_exists('fiscal_next_nfce_number', $data) && $data['fiscal_next_nfce_number'] !== null ? (int) $data['fiscal_next_nfce_number'] : null,
            fiscalNextNfseNumber: array_key_exists('fiscal_next_nfse_number', $data) && $data['fiscal_next_nfse_number'] !== null ? (int) $data['fiscal_next_nfse_number'] : null,
            fiscalNfceCscId: $data['fiscal_nfce_csc_id'] ?? null,
            fiscalNfceCscCode: $data['fiscal_nfce_csc_code'] ?? null,
            fiscalProviderApiToken: $data['fiscal_provider_api_token'] ?? null,
            fiscalCertificateA1: $data['fiscal_certificate_a1'] ?? null,
            fiscalCertificateA1Password: $data['fiscal_certificate_a1_password'] ?? null,
            clearFiscalNfceCscCode: filter_var($data['clear_fiscal_nfce_csc_code'] ?? false, FILTER_VALIDATE_BOOLEAN),
            clearFiscalProviderApiToken: filter_var($data['clear_fiscal_provider_api_token'] ?? false, FILTER_VALIDATE_BOOLEAN),
            clearFiscalCertificateA1: filter_var($data['clear_fiscal_certificate_a1'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }
}
