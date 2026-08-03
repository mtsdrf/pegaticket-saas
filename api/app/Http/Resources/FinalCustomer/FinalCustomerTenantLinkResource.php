<?php

namespace App\Http\Resources\FinalCustomer;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape de comprador pro staff (fluxo de venda manual, SaleFormPage):
 * combina a identidade global (FinalCustomer, eager-loaded) com os campos
 * por-tenant (FinalCustomerTenantLink). uuid retornado é o do LINK — é ele
 * quem carrega os dados do tenant atual e é o que o staff usa pra
 * selecionar o comprador na tela; final_customer.uuid vai junto pra quem
 * precisar da identidade global.
 */
class FinalCustomerTenantLinkResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'cpf_cnpj' => $this->cpf_cnpj,
            'phone_primary' => $this->phone_primary,
            'phone_secondary' => $this->phone_secondary,
            'is_active' => $this->is_active,
            'is_trusted' => $this->is_trusted,
            'final_customer' => [
                'uuid' => $this->finalCustomer->uuid,
                'name' => $this->finalCustomer->name,
                'last_name' => $this->finalCustomer->last_name,
                'email' => $this->finalCustomer->email,
            ],
        ];
    }
}
