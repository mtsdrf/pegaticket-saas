<?php

namespace App\Repositories\Contracts;

use App\Models\Storefront\ProductPromotion;
use Illuminate\Support\Collection;

interface ProductPromotionRepositoryInterface extends BaseRepositoryInterface
{
    public function listForTenant(int $tenantId): Collection;

    /**
     * Um produto só pode ter 1 promoção por tenant — atualiza em vez de
     * duplicar quando já existe (inclusive se a linha existente estava
     * soft-deletada, restaura em vez de colidir com a unique
     * (tenant_id, ticket_type_id)).
     */
    public function upsertForTenant(
        int $tenantId,
        int $ticketTypeId,
        ?float $promoPrice,
        ?string $startsAt,
        ?string $expiresAt,
        string $discountType = 'fixed_price',
        ?float $discountPercentage = null
    ): ProductPromotion;

    /**
     * Coleção de promoções ATIVAS e dentro da janela de datas, keyed por
     * ticket_type_id — usada por StorefrontCatalogService::paginateProducts()
     * para evitar N+1 ao montar a página inteira de produtos de uma vez.
     */
    public function activePromotionsForProducts(int $tenantId, array $productIds): Collection;

    /**
     * Promoção ATIVA de UM produto (checkout) — mesma regra de
     * elegibilidade de activePromotionsForProducts(), só que para 1 item.
     * Retorna o model (não só o preço) porque o preço efetivo de
     * 'percentage' depende do TicketType.price vigente, resolvido pelo
     * chamador via ProductPromotion::effectivePrice().
     */
    public function findActivePromotion(int $tenantId, int $ticketTypeId): ?ProductPromotion;
}
