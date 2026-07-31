<?php

namespace App\Services\Storefront;

use App\Exceptions\InvalidOrderStateException;
use App\Exceptions\OrderAlreadyRatedException;
use App\Models\Storefront\OrderRating;
use App\Services\Portal\PortalCustomerService;

/**
 * Avaliação do cliente final sobre um pedido entregue (roadmap Delivery,
 * Fase 4 — retenção). Sem Repository dedicado, mesma decisão de
 * ProductFavoriteService. Reaproveita
 * PortalCustomerService::findOwnedOrder() para a checagem de posse (pedido
 * precisa pertencer a um Client vinculado a este FinalCustomer via
 * FinalCustomerTenantLink confirmado) — mesma lógica usada pelo reorder,
 * sem duplicar.
 */
class OrderRatingService
{
    public function __construct(
        private PortalCustomerService $portalCustomerService,
    ) {
    }

    public function rate(int $finalCustomerId, string $orderUuid, int $rating, ?string $comment): OrderRating
    {
        $order = $this->portalCustomerService->findOwnedOrder($finalCustomerId, $orderUuid);

        if (!$order->is_delivered) {
            throw new InvalidOrderStateException(__('messages.order.not_delivered'));
        }

        if (OrderRating::where('order_id', $order->id)->exists()) {
            throw new OrderAlreadyRatedException(__('messages.storefront.order_already_rated'));
        }

        return OrderRating::create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'final_customer_id' => $finalCustomerId,
            'rating' => $rating,
            'comment' => $comment,
        ]);
    }

    /**
     * Agregado simples exibido em StorefrontTenantResource
     * (average_rating/ratings_count). null quando o tenant ainda não tem
     * nenhuma avaliação (nunca 0.0 — distinção relevante pro frontend).
     */
    public function tenantSummary(int $tenantId): array
    {
        $stats = OrderRating::where('tenant_id', $tenantId)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as ratings_count')
            ->first();

        $count = (int) ($stats->ratings_count ?? 0);

        return [
            'average_rating' => $count > 0 ? round((float) $stats->avg_rating, 1) : null,
            'ratings_count' => $count,
        ];
    }
}
