<?php

namespace App\Services\Portal;

use App\DTOs\Portal\CreatePortalLinkDTO;
use App\Events\Portal\PortalLinkConfirmed;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Sale\Sale;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Confirmação explícita de vínculo entre o cliente final (autenticado no
 * Portal) e uma loja (tenant), a partir de uma venda que ele já tem em
 * mãos (sale_uuid já validado no FormRequest via Rule::exists — mesmo
 * padrão de FK cross-tabela do resto do projeto) — o sale_uuid é só PROVA
 * de que ele é cliente real dessa loja, não precisa ser uma venda feito
 * por ESTE FinalCustomer (order.final_customer_id não muda aqui: a venda
 * já nasceu vinculado ao seu comprador original em SaleService::create()
 * ou StorefrontCheckoutService::checkout()). Idempotente: chamar de novo
 * para o mesmo tenant não duplica nem dispara evento de novo, só retorna o
 * vínculo já existente.
 */
class PortalLinkService
{
    public function __construct(
        private FinalCustomerTenantLinkRepositoryInterface $linkRepository,
    ) {
    }

    public function link(FinalCustomer $customer, CreatePortalLinkDTO $dto): FinalCustomerTenantLink
    {
        return DB::transaction(function () use ($customer, $dto) {
            $order = Sale::where('uuid', $dto->saleUuid)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $existing = $this->linkRepository->findByCustomerAndTenant($customer->id, (int) $order->tenant_id);

            if ($existing) {
                return $existing;
            }

            $link = $this->linkRepository->create([
                'final_customer_id' => $customer->id,
                'tenant_id' => $order->tenant_id,
                'confirmed_at' => now(),
            ]);

            $link->load('tenant');

            event(new PortalLinkConfirmed(
                finalCustomerUuid: $customer->uuid,
                tenantUuid: $link->tenant->uuid,
            ));

            return $link;
        });
    }
}
