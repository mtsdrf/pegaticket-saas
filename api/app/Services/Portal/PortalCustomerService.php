<?php

namespace App\Services\Portal;

use App\Models\Event\EventProduct;
use App\Models\Event\TicketType;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Models\Ticket\Ticket;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Leitura agregada do cliente final autenticado: vendas de todas as lojas
 * vinculadas E confirmadas, e o perfil básico (GET /portal/sales,
 * GET /portal/me).
 */
class PortalCustomerService
{
    public function __construct(
        private FinalCustomerTenantLinkRepositoryInterface $linkRepository,
    ) {}

    public function listOrders(FinalCustomer $customer): Collection
    {
        $links = $this->linkRepository->confirmedLinksFor($customer->id);

        if ($links->isEmpty()) {
            return collect();
        }

        $tenantIds = $links->pluck('tenant_id')->all();

        return Sale::query()
            ->whereNull('deleted_at')
            ->where('final_customer_id', $customer->id)
            ->whereIn('tenant_id', $tenantIds)
            ->with(['tenant', 'coupon'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function me(FinalCustomer $customer): FinalCustomer
    {
        $customer->load(['links' => function ($query) {
            $query->whereNotNull('confirmed_at')->with('tenant');
        }]);

        return $customer;
    }

    /**
     * Checagem de posse compartilhada por qualquer recurso "minha compra" do
     * portal (reorder, avaliação): a venda precisa pertencer a um Client
     * vinculado a este FinalCustomer via FinalCustomerTenantLink confirmado
     * — mesma lógica de listOrders(), sem duplicar. 404 (não 403) tanto
     * para uuid inexistente quanto para venda de outra loja não vinculada,
     * nunca vazando qual dos dois casos é.
     */
    public function findOwnedOrder(int $finalCustomerId, string $saleUuid): Sale
    {
        $order = Sale::where('uuid', $saleUuid)->whereNull('deleted_at')->first();

        if (! $order || (int) $order->final_customer_id !== $finalCustomerId) {
            abort(404);
        }

        $link = $this->linkRepository->findConfirmedByTenantAndFinalCustomer((int) $order->tenant_id, $finalCustomerId);

        if (! $link) {
            abort(404);
        }

        return $order;
    }

    /**
     * "Titularidade e transferência" (roadmap Fase 4) — mesma checagem de
     * posse do findOwnedOrder, mas navegando do Ticket pra Sale (só o
     * comprador dono da venda pode transferir um ingresso dela).
     */
    public function findOwnedTicket(int $finalCustomerId, string $ticketUuid): Ticket
    {
        $ticket = Ticket::query()
            ->with('saleItem.sale')
            ->where('uuid', $ticketUuid)
            ->whereNull('deleted_at')
            ->first();

        $sale = $ticket?->saleItem?->sale;

        if (! $ticket || ! $sale || (int) $sale->final_customer_id !== $finalCustomerId) {
            abort(404);
        }

        $link = $this->linkRepository->findConfirmedByTenantAndFinalCustomer((int) $sale->tenant_id, $finalCustomerId);

        if (! $link) {
            abort(404);
        }

        return $ticket;
    }

    /**
     * "Pedir de novo" (roadmap Delivery, Fase 4 — retenção): itens do
     * venda antigo, com preço ATUAL do ingresso/produto do evento (não o
     * preço congelado na venda) e disponibilidade atual. withTrashed()
     * porque o nome histórico do item precisa aparecer mesmo se o item
     * foi removido desde então — nesse caso is_available fica false.
     */
    public function getSaleItemsForReorder(FinalCustomer $customer, string $saleUuid): array
    {
        $order = $this->findOwnedOrder($customer->id, $saleUuid);
        $order->load('items');

        return $order->items->map(function ($item) {
            $sellable = $item->ticket_type_id !== null
                ? TicketType::withTrashed()->find($item->ticket_type_id)
                : EventProduct::withTrashed()->find($item->event_product_id);

            return [
                'ticket_type_uuid' => $sellable?->uuid,
                'ticket_type_name' => $sellable?->name,
                'quantity' => (float) $item->quantity,
                'current_price' => $sellable ? (float) $sellable->price : null,
                'is_available' => $sellable
                    ? (! $sellable->trashed() && ($item->ticket_type_id === null || $sellable->status === 'ativo'))
                    : false,
            ];
        })->values()->all();
    }
}
