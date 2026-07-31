<?php

namespace App\Http\Resources\Stock;

use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost !== null ? (float) $this->unit_cost : null,
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'product' => $this->whenLoaded('product', fn() => [
                'uuid' => $this->product->uuid,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'location' => $this->whenLoaded('location', fn() => [
                'uuid' => $this->location->uuid,
                'name' => $this->location->name,
            ]),
            'destination_location' => $this->whenLoaded('destinationLocation', fn() => $this->destinationLocation ? [
                'uuid' => $this->destinationLocation->uuid,
                'name' => $this->destinationLocation->name,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
