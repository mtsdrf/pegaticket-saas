<?php

namespace App\Http\Resources\TenantSettings;

use Illuminate\Http\Resources\Json\JsonResource;

class TenantSettingsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'send_tracking_link_whatsapp' => $this->send_tracking_link_whatsapp,
            'minimum_order_value' => $this->minimum_order_value,
            'estimated_preparation_minutes' => $this->estimated_preparation_minutes,
            'accepted_payment_methods' => $this->accepted_payment_methods ?? [],
            'payment_receiving_method' => $this->payment_receiving_method ?? 'manual',
            'payment_pix_key' => $this->payment_pix_key,
            'storefront_enabled' => $this->storefront_enabled,
            'catalog_layout' => $this->catalog_layout ?? 'list',
            'hold_duration_minutes' => $this->hold_duration_minutes,
        ];
    }
}
