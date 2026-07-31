<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceCatalogSyncResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'categories_total' => (int) $this->categories_total,
            'items_total' => (int) $this->items_total,
            'processed_count' => (int) $this->processed_count,
            'success_count' => (int) $this->success_count,
            'failed_count' => (int) $this->failed_count,
            'started_at' => optional($this->started_at)?->toIso8601String(),
            'finished_at' => optional($this->finished_at)?->toIso8601String(),
            'error_message' => $this->error_message,
            'request_snapshot' => $this->request_snapshot,
            'response_snapshot' => $this->response_snapshot,
            'merchant' => $this->whenLoaded('merchant', fn () => $this->merchant ? [
                'uuid' => $this->merchant->uuid,
                'external_id' => $this->merchant->external_id,
                'name' => $this->merchant->name,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => MarketplaceCatalogSyncItemResource::collection($this->items)),
        ];
    }
}
