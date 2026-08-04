<?php

namespace App\Http\Resources\FinalCustomer;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CRM básico do comprador (Fase 6, fatia final) — agregação de dados já
 * existentes (FinalCustomer/FinalCustomerTenantLink/Sale), NÃO um CRM
 * completo: sem nota/tags customizadas (não existem no schema). Shape vem
 * pronto de `FinalCustomerTenantLinkRepository::crmSummaryForTenant()`
 * (stdClass de uma query agregada), por isso os acessos abaixo batem 1:1
 * com os aliases do SELECT.
 */
class FinalCustomerCrmResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->link_uuid,
            'final_customer_uuid' => $this->final_customer_uuid,
            'name' => $this->name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone_primary' => $this->phone_primary,
            'total_spent' => (float) $this->total_spent,
            'purchase_count' => (int) $this->purchase_count,
            'last_purchase_at' => $this->last_purchase_at,
        ];
    }
}
