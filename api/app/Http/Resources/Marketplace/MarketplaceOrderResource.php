<?php

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'external_id' => $this->external_id,
            'display_id' => $this->display_id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'queue_status' => $this->queue_status ?? (
                $this->internal_order_id !== null
                    ? 'imported'
                    : ($this->import_error_message ? 'import_error' : 'pending_import')
            ),
            'customer_name' => $this->customer_name,
            'total_amount' => $this->total_amount,
            'raw_updated_at' => optional($this->raw_updated_at)?->toIso8601String(),
            'last_synced_at' => optional($this->last_synced_at)?->toIso8601String(),
            'imported_at' => optional($this->imported_at)?->toIso8601String(),
            'import_error_message' => $this->import_error_message,
            'events_count' => $this->when($this->resource->getAttribute('events_count') !== null, (int) $this->resource->getAttribute('events_count')),
            'latest_event_at' => $this->resource->getAttribute('latest_event_at')
                ? optional($this->resource->getAttribute('latest_event_at'))->toIso8601String()
                : null,
            'payload' => $this->payload,
            'merchant' => $this->whenLoaded('merchant', fn () => new MarketplaceMerchantResource($this->merchant)),
            'actions' => $this->whenLoaded('actions', fn () => MarketplaceActionResource::collection($this->actions)),
            'events' => $this->whenLoaded('events', fn () => MarketplaceEventResource::collection($this->events)),
            'internal_order' => $this->whenLoaded('internalOrder', fn () => $this->internalOrder ? [
                'uuid' => $this->internalOrder->uuid,
                'codigo' => $this->internalOrder->codigo,
                'status' => $this->internalOrder->status,
                'origin' => $this->internalOrder->origin,
                'is_paid' => $this->internalOrder->is_paid,
                'is_delivered' => $this->internalOrder->is_delivered,
                'client_name' => $this->internalOrder->client?->name,
            ] : null),
        ];
    }
}
