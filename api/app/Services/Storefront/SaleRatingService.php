<?php

namespace App\Services\Storefront;

use App\Exceptions\InvalidSaleStateException;
use App\Exceptions\SaleAlreadyRatedException;
use App\Models\Storefront\SaleRating;
use App\Services\Portal\PortalCustomerService;

/**
 * Avaliação do cliente final sobre uma venda entregue (roadmap Delivery,
 * Fase 4 — retenção). Sem Repository dedicado, mesma decisão de
 * ProductFavoriteService. Reaproveita
 * PortalCustomerService::findOwnedOrder() para a checagem de posse (venda
 * precisa pertencer a um Client vinculado a este FinalCustomer via
 * FinalCustomerTenantLink confirmado) — mesma lógica usada pelo reorder,
 * sem duplicar.
 */
class SaleRatingService
{
    public function __construct(
        private PortalCustomerService $portalCustomerService,
    ) {
    }

    public function rate(int $finalCustomerId, string $saleUuid, int $rating, ?string $comment): SaleRating
    {
        $order = $this->portalCustomerService->findOwnedOrder($finalCustomerId, $saleUuid);

        if (!$order->is_paid) {
            throw new InvalidSaleStateException(__('messages.sale.not_completed'));
        }

        if (SaleRating::where('sale_id', $order->id)->exists()) {
            throw new SaleAlreadyRatedException(__('messages.storefront.order_already_rated'));
        }

        return SaleRating::create([
            'tenant_id' => $order->tenant_id,
            'sale_id' => $order->id,
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
        $stats = SaleRating::where('tenant_id', $tenantId)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as ratings_count')
            ->first();

        $count = (int) ($stats->ratings_count ?? 0);

        return [
            'average_rating' => $count > 0 ? round((float) $stats->avg_rating, 1) : null,
            'ratings_count' => $count,
        ];
    }
}
