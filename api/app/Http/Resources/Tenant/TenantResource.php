<?php

namespace App\Http\Resources\Tenant;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'plan_uuid' => $this->plan?->uuid ?? $this->plan_uuid ?? null,
            'plan_name' => $this->plan?->name ?? $this->plan_name ?? null,
            'logo_url' => MediaUrl::resolvePublic(
                $this->logo_path,
                $this->logo_data ?? $this->logo_mime,
                '/api/v1/tenants/' . $this->uuid . '/logo',
                $this->logo_updated_at,
                'tenant'
            ),
            'is_active' => $this->is_active,
            'trial_ends_at' => optional($this->trial_ends_at)?->toDateTimeString(),
            // Cadastro fiscal do emitente (roadmap Fiscal D0)
            'cnpj' => $this->cnpj,
            'ie' => $this->ie,
            'im' => $this->im,
            'cnae' => $this->cnae,
            'tax_regime' => $this->tax_regime,
            'fiscal_environment' => $this->fiscal_environment,
            'ibge_city_code' => $this->ibge_city_code,
            'fiscal_provider' => $this->fiscal_provider,
            'fiscal_nfe_series' => $this->fiscal_nfe_series,
            'fiscal_nfce_series' => $this->fiscal_nfce_series,
            'fiscal_nfse_series' => $this->fiscal_nfse_series,
            'fiscal_next_nfe_number' => $this->fiscal_next_nfe_number,
            'fiscal_next_nfce_number' => $this->fiscal_next_nfce_number,
            'fiscal_next_nfse_number' => $this->fiscal_next_nfse_number,
            'fiscal_nfce_csc_id' => $this->fiscal_nfce_csc_id,
            'has_fiscal_nfce_csc_code' => !empty($this->fiscal_nfce_csc_code),
            'has_fiscal_provider_api_token' => !empty($this->fiscal_provider_api_token),
            'has_fiscal_certificate_a1' => !empty($this->fiscal_certificate_a1_data),
            'fiscal_certificate_a1_name' => $this->fiscal_certificate_a1_name,
            'fiscal_certificate_a1_updated_at' => $this->fiscal_certificate_a1_updated_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
