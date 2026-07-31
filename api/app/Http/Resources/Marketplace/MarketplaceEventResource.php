<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'external_event_id' => $this->external_event_id,
            'external_order_id' => $this->external_order_id,
            'event_type' => $this->event_type,
            'event_full_code' => $this->event_full_code,
            'status' => $this->status,
            'processing_attempts' => (int) $this->processing_attempts,
            'occurred_at' => optional($this->occurred_at)?->toIso8601String(),
            'acknowledged_at' => optional($this->acknowledged_at)?->toIso8601String(),
            'processed_at' => optional($this->processed_at)?->toIso8601String(),
            'last_attempted_at' => optional($this->last_attempted_at)?->toIso8601String(),
            'dead_lettered_at' => optional($this->dead_lettered_at)?->toIso8601String(),
            'error_message' => $this->error_message,
            'payload' => $this->payload,
        ];
    }
}
