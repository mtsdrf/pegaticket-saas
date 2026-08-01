<?php

namespace App\Services\Storefront;

use App\Events\Storefront\ProductPromotionDeleted;
use App\Events\Storefront\ProductPromotionUpserted;
use App\Models\Event\TicketType;
use App\Models\Storefront\ProductPromotion;
use App\Repositories\Contracts\ProductPromotionRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Preço promocional "de/por" por produto — upsert 1 por chave (aqui, por
 * ticket_type_id), nunca duplica.
 */
class ProductPromotionService
{
    public function __construct(
        private ProductPromotionRepositoryInterface $repository
    ) {
    }

    public function list(int $tenantId): Collection
    {
        return $this->repository->listForTenant($tenantId);
    }

    public function upsert(
        int $tenantId,
        string $ticketTypeUuid,
        ?float $promoPrice,
        ?string $startsAt,
        ?string $expiresAt,
        string $discountType = 'fixed_price',
        ?float $discountPercentage = null
    ): ProductPromotion {
        return DB::transaction(function () use ($tenantId, $ticketTypeUuid, $promoPrice, $startsAt, $expiresAt, $discountType, $discountPercentage) {
            $ticketType = TicketType::where('uuid', $ticketTypeUuid)
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $promotion = $this->repository->upsertForTenant(
                $tenantId,
                $ticketType->id,
                $promoPrice,
                $startsAt,
                $expiresAt,
                $discountType,
                $discountPercentage
            );

            event(new ProductPromotionUpserted(productPromotionUuid: $promotion->uuid, actorId: Auth::id()));

            return $promotion;
        });
    }

    public function delete(int $tenantId, string $uuid): void
    {
        DB::transaction(function () use ($tenantId, $uuid) {
            $promotion = ProductPromotion::where('uuid', $uuid)
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $this->repository->delete($promotion);

            event(new ProductPromotionDeleted(productPromotionUuid: $uuid, actorId: Auth::id()));
        });
    }

    /**
     * Preço promocional EFETIVO de um produto — usado pelo checkout
     * (StorefrontCheckoutService::resolveEffectiveUnitPrice(), 1 produto por
     * vez). Recebe o Product inteiro (não só o id) porque o preço efetivo
     * de uma promoção 'percentage' depende do TicketType.price VIGENTE no
     * momento da chamada — nunca congelado. Ver
     * ProductPromotion::effectivePrice().
     */
    public function findActivePromoPrice(TicketType $ticketType): ?float
    {
        $promotion = $this->repository->findActivePromotion($ticketType->tenant_id, $ticketType->id);

        return $promotion?->effectivePrice((float) $ticketType->price);
    }

    /**
     * Coleção de promoções ativas keyed por ticket_type_id — repassada pelo
     * StorefrontCatalogService pro StorefrontProductResource sem N+1.
     */
    public function activePromotionsForProducts(int $tenantId, array $productIds): Collection
    {
        return $this->repository->activePromotionsForProducts($tenantId, $productIds);
    }
}
