<?php

namespace App\Services\Portal;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Leitura agregada do cliente final autenticado: pedidos de todas as lojas
 * vinculadas E confirmadas, e o perfil básico (GET /portal/orders,
 * GET /portal/me).
 */
class PortalCustomerService
{
    public function __construct(
        private FinalCustomerTenantLinkRepositoryInterface $linkRepository,
    ) {
    }

    public function listOrders(FinalCustomer $customer): Collection
    {
        $links = $this->linkRepository->confirmedLinksFor($customer->id);

        if ($links->isEmpty()) {
            return collect();
        }

        return Order::query()
            ->whereNull('deleted_at')
            ->where(function ($query) use ($links) {
                foreach ($links as $link) {
                    $query->orWhere(function ($sub) use ($link) {
                        $sub->where('tenant_id', $link->tenant_id)
                            ->where('client_id', $link->client_id);
                    });
                }
            })
            ->with(['tenant', 'coupon'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function me(FinalCustomer $customer): FinalCustomer
    {
        $customer->load(['links' => function ($query) {
            $query->whereNotNull('confirmed_at')->with(['tenant', 'client']);
        }]);

        return $customer;
    }

    /**
     * Checagem de posse compartilhada por qualquer recurso "meu pedido" do
     * portal (reorder, avaliação): o pedido precisa pertencer a um Client
     * vinculado a este FinalCustomer via FinalCustomerTenantLink confirmado
     * — mesma lógica de listOrders(), sem duplicar. 404 (não 403) tanto
     * para uuid inexistente quanto para pedido de outra loja não vinculada,
     * nunca vazando qual dos dois casos é.
     */
    public function findOwnedOrder(int $finalCustomerId, string $orderUuid): Order
    {
        $order = Order::where('uuid', $orderUuid)->whereNull('deleted_at')->first();

        if (!$order) {
            abort(404);
        }

        $link = $this->linkRepository->findByCustomerAndClient($finalCustomerId, $order->client_id);

        if (!$link || (int) $link->tenant_id !== (int) $order->tenant_id) {
            abort(404);
        }

        return $order;
    }

    /**
     * "Pedir de novo" (roadmap Delivery, Fase 4 — retenção): itens do
     * pedido antigo, com preço ATUAL do produto (não o preço congelado no
     * pedido) e disponibilidade atual. withTrashed() no Product porque o
     * nome histórico do item precisa aparecer mesmo se o produto foi
     * removido desde então — nesse caso is_available fica false.
     */
    public function getOrderItemsForReorder(FinalCustomer $customer, string $orderUuid): array
    {
        $order = $this->findOwnedOrder($customer->id, $orderUuid);
        $order->load('items');

        return $order->items->map(function ($item) {
            $product = Product::withTrashed()->find($item->product_id);

            return [
                'product_uuid' => $product?->uuid,
                'product_name' => $product?->name,
                'quantity' => (float) $item->quantity,
                'current_price' => $product ? (float) $product->price : null,
                'is_available' => $product ? (!$product->trashed() && (bool) $product->is_available) : false,
            ];
        })->values()->all();
    }
}
