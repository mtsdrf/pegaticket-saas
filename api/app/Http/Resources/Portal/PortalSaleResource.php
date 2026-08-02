<?php

namespace App\Http\Resources\Portal;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mesmo shape enxuto de SalePublicTrackingResource (Fase 5.1), sem
 * items/installments — listagem agregada entre lojas, detalhe completo de
 * um pedido continua sendo GET /rastreio/{uuid} (link que o cliente já tem).
 */
class PortalSaleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'tenant_name' => $this->whenLoaded('tenant', fn() => $this->tenant->name),
            'tenant_slug' => $this->whenLoaded('tenant', fn() => $this->tenant->slug),
            'is_paid' => $this->is_paid,
            'is_completed' => $this->is_completed,
            'status' => $this->status,
            'total_amount' => $this->total_amount,
            'discount_amount' => (float) $this->discount_amount,
            'coupon_code' => $this->whenLoaded('coupon', fn() => $this->coupon?->code),
            'is_cancelled' => $this->cancelled_at !== null,
            'created_at' => $this->created_at,
        ];
    }
}
