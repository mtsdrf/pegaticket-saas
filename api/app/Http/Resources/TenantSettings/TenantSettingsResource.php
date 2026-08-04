<?php

namespace App\Http\Resources\TenantSettings;

use Illuminate\Http\Resources\Json\JsonResource;

class TenantSettingsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'accepted_payment_methods' => $this->accepted_payment_methods ?? [],
            'payment_receiving_method' => $this->payment_receiving_method ?? 'manual',
            'payment_pix_key' => $this->payment_pix_key,
            'pagbank_integration_mode' => $this->pagbank_integration_mode ?? 'disabled',
            'pagbank_environment' => $this->pagbank_environment ?? 'sandbox',
            'has_pagbank_access_token' => ! empty($this->pagbank_access_token),
            'pagbank_receiver_account_id' => $this->pagbank_receiver_account_id,
            'storefront_enabled' => $this->storefront_enabled,
            'catalog_layout' => $this->catalog_layout ?? 'list',
            'hold_duration_minutes' => $this->hold_duration_minutes,
            'affiliate_default_commission_percentage' => $this->affiliate_default_commission_percentage !== null
                ? (float) $this->affiliate_default_commission_percentage
                : null,
            'meta_pixel_id' => $this->meta_pixel_id,
            'google_analytics_id' => $this->google_analytics_id,
        ];
    }
}
