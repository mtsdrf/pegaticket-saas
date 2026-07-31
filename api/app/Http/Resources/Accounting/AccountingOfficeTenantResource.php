<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vínculo contador <-> tenant. Serve os dois lados: para o tenant (lista de
 * pendências) inclui dados do escritório; para o contador (lista dos seus
 * vínculos) inclui dados do tenant. Ambos são carregados via `whenLoaded`.
 */
class AccountingOfficeTenantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'scopes' => $this->scopes ?? [],
            'requested_at' => optional($this->requested_at)->toIso8601String(),
            'approved_at' => optional($this->approved_at)->toIso8601String(),
            'revoked_at' => optional($this->revoked_at)->toIso8601String(),
            'accounting_office' => $this->whenLoaded('accountingOffice', fn () => [
                'uuid' => $this->accountingOffice->uuid,
                'cnpj' => $this->accountingOffice->cnpj,
                'company_name' => $this->accountingOffice->company_name,
                'responsible_name' => $this->accountingOffice->responsible_name,
                'email' => $this->accountingOffice->email,
            ]),
            'tenant' => $this->whenLoaded('tenant', fn () => [
                'uuid' => $this->tenant->uuid,
                'name' => $this->tenant->name,
                'cnpj' => $this->tenant->cnpj,
            ]),
        ];
    }
}
