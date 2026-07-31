<?php

namespace App\Services\Portal;

use App\DTOs\Portal\CreatePortalLinkDTO;
use App\Events\Portal\PortalLinkConfirmed;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Order\Order;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Confirmação explícita de vínculo entre o cliente final e uma loja
 * (tenant), a partir de um pedido que ele já tem em mãos (order_uuid já
 * validado no FormRequest via Rule::exists — mesmo padrão de FK cross-tabela
 * do resto do projeto). Idempotente: chamar de novo para o mesmo Client não
 * duplica nem dispara evento de novo, só retorna o vínculo já existente.
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
            $order = Order::where('uuid', $dto->orderUuid)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $existing = $this->linkRepository->findByCustomerAndClient($customer->id, $order->client_id);

            if ($existing) {
                return $existing;
            }

            $link = $this->linkRepository->create([
                'final_customer_id' => $customer->id,
                'tenant_id' => $order->tenant_id,
                'client_id' => $order->client_id,
                'confirmed_at' => now(),
            ]);

            $link->load(['tenant', 'client']);

            event(new PortalLinkConfirmed(
                finalCustomerUuid: $customer->uuid,
                tenantUuid: $link->tenant->uuid,
                clientUuid: $link->client->uuid,
            ));

            return $link;
        });
    }
}
