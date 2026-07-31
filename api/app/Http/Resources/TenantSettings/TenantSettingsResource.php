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
            'block_order_without_stock' => $this->block_order_without_stock,
            'minimum_order_value' => $this->minimum_order_value,
            'estimated_preparation_minutes' => $this->estimated_preparation_minutes,
            'cashback_enabled' => $this->cashback_enabled,
            'cashback_percentage' => $this->cashback_percentage,
            'cashback_max_per_order' => $this->cashback_max_per_order,
            'cashback_hold_days' => $this->cashback_hold_days,
            'cashback_expiration_days' => $this->cashback_expiration_days,
            'cashback_redeem_max_percentage' => $this->cashback_redeem_max_percentage,
            'cashback_name' => $this->cashback_name,
            'accepted_payment_methods' => $this->accepted_payment_methods ?? [],
            'payment_receiving_method' => $this->payment_receiving_method ?? 'manual',
            'payment_pix_key' => $this->payment_pix_key,
            'allow_store_pickup' => $this->allow_store_pickup,
            'allow_delivery' => $this->allow_delivery,
            'storefront_enabled' => $this->storefront_enabled,
            'catalog_layout' => $this->catalog_layout ?? 'list',
        ];
    }
}
