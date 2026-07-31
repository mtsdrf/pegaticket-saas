<?php

namespace App\DTOs\Storefront;

class UpsertProductPromotionDTO
{
    public function __construct(
        public readonly string $productUuid,
        public readonly ?float $promoPrice,
        public readonly ?string $startsAt,
        public readonly ?string $expiresAt,
        // 'fixed_price' (default, "de/por" absoluto) | 'percentage'
        // (desconto % sobre o Product.price vigente, ver
        // ProductPromotion::effectivePrice()).
        public readonly string $discountType = 'fixed_price',
        public readonly ?float $discountPercentage = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            productUuid: $data['product_uuid'],
            promoPrice: isset($data['promo_price']) ? (float) $data['promo_price'] : null,
            startsAt: $data['starts_at'] ?? null,
            expiresAt: $data['expires_at'] ?? null,
            discountType: $data['discount_type'] ?? 'fixed_price',
            discountPercentage: isset($data['discount_percentage']) ? (float) $data['discount_percentage'] : null,
        );
    }
}
