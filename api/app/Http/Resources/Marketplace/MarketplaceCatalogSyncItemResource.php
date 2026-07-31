<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceCatalogSyncItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'entity_type' => $this->entity_type,
            'entity_key' => $this->entity_key,
            'external_entity_id' => $this->external_entity_id,
            'batch_id' => $this->batch_id,
            'status' => $this->status,
            'processed_at' => optional($this->processed_at)?->toIso8601String(),
            'error_message' => $this->error_message,
            'request_payload' => $this->request_payload,
            'response_payload' => $this->response_payload,
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'uuid' => $this->product->uuid,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ] : null),
        ];
    }
}
