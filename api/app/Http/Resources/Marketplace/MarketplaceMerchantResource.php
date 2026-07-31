<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceMerchantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'external_id' => $this->external_id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'status_payload' => $this->status_payload,
            'metadata' => $this->metadata,
            'last_seen_at' => optional($this->last_seen_at)?->toIso8601String(),
        ];
    }
}
