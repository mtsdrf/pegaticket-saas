<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->value !== null ? (float) $this->value : null,
            'minimum_order_value' => $this->minimum_order_value !== null ? (float) $this->minimum_order_value : null,
            'max_uses_total' => $this->max_uses_total,
            'max_uses_per_customer' => $this->max_uses_per_customer,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_active' => $this->is_active,
            // null = sem restrição (vale para todos os meios) — nunca
            // normalizar para [] aqui, é um significado diferente
            // ("nenhum meio permitido").
            'allowed_payment_methods' => $this->allowed_payment_methods,
        ];
    }
}
