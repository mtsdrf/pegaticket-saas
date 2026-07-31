<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceIntegrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'provider' => $this->provider,
            'name' => $this->name,
            'environment' => $this->environment,
            'auth_mode' => $this->auth_mode,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'client_id' => $this->client_id,
            'merchant_id' => $this->merchant_id,
            'webhook_url' => $this->webhook_url,
            'generated_webhook_url' => app(\App\Services\Marketplace\MarketplaceIntegrationService::class)->generateWebhookUrl($this->resource),
            'polling_merchant_ids' => $this->polling_merchant_ids,
            'access_token_expires_at' => optional($this->access_token_expires_at)?->toIso8601String(),
            'refresh_token_expires_at' => optional($this->refresh_token_expires_at)?->toIso8601String(),
            'last_connected_at' => optional($this->last_connected_at)?->toIso8601String(),
            'last_synced_at' => optional($this->last_synced_at)?->toIso8601String(),
            'last_polled_at' => optional($this->last_polled_at)?->toIso8601String(),
            'last_error_at' => optional($this->last_error_at)?->toIso8601String(),
            'last_error_message' => $this->last_error_message,
            'settings' => $this->settings,
            'merchants' => $this->whenLoaded('merchants', fn () => MarketplaceMerchantResource::collection($this->merchants)),
            'merchants_count' => $this->whenCounted('merchants'),
            'events_count' => $this->whenCounted('events'),
            'orders_count' => $this->whenCounted('orders'),
        ];
    }
}
