<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductPromotionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'discount_type' => $this->discount_type,
            'promo_price' => $this->promo_price !== null ? (float) $this->promo_price : null,
            'discount_percentage' => $this->discount_percentage !== null ? (float) $this->discount_percentage : null,
            // Preço final calculado — 'percentage' em cima do Product.price
            // vigente (whenLoaded, evita N+1); 'fixed_price' é o próprio
            // promo_price. Ver ProductPromotion::effectivePrice().
            'effective_price' => $this->whenLoaded('product', fn() => $this->effectivePrice((float) $this->product->price)),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'product' => $this->whenLoaded('product', fn() => [
                'uuid' => $this->product->uuid,
                'name' => $this->product->name,
            ]),
        ];
    }
}
