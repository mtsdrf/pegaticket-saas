<?php

namespace App\Services\Storefront;

use App\Events\Storefront\ProductPromotionDeleted;
use App\Events\Storefront\ProductPromotionUpserted;
use App\Models\Product\Product;
use App\Models\Storefront\ProductPromotion;
use App\Repositories\Contracts\ProductPromotionRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Preço promocional "de/por" por produto (roadmap Delivery, Fase 3) — mesmo
 * shape de StoreDeliveryFeeService: upsert 1 por chave (aqui, por
 * product_id), nunca duplica.
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
        string $productUuid,
        ?float $promoPrice,
        ?string $startsAt,
        ?string $expiresAt,
        string $discountType = 'fixed_price',
        ?float $discountPercentage = null
    ): ProductPromotion {
        return DB::transaction(function () use ($tenantId, $productUuid, $promoPrice, $startsAt, $expiresAt, $discountType, $discountPercentage) {
            $product = Product::where('uuid', $productUuid)
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $promotion = $this->repository->upsertForTenant(
                $tenantId,
                $product->id,
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
     * de uma promoção 'percentage' depende do Product.price VIGENTE no
     * momento da chamada — nunca congelado. Ver
     * ProductPromotion::effectivePrice().
     */
    public function findActivePromoPrice(Product $product): ?float
    {
        $promotion = $this->repository->findActivePromotion($product->tenant_id, $product->id);

        return $promotion?->effectivePrice((float) $product->price);
    }

    /**
     * Coleção de promoções ativas keyed por product_id — repassada pelo
     * StorefrontCatalogService pro StorefrontProductResource sem N+1.
     */
    public function activePromotionsForProducts(int $tenantId, array $productIds): Collection
    {
        return $this->repository->activePromotionsForProducts($tenantId, $productIds);
    }
}
