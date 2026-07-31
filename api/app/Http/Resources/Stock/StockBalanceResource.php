<?php

namespace App\Http\Resources\Stock;

use Illuminate\Http\Resources\Json\JsonResource;

class StockBalanceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'quantity_on_hand' => $this->quantity_on_hand,
            'quantity_reserved' => $this->quantity_reserved,
            'quantity_blocked' => $this->quantity_blocked,
            'quantity_available' => $this->quantity_available,
            'product' => $this->whenLoaded('product', fn() => [
                'uuid' => $this->product->uuid,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'location' => $this->whenLoaded('location', fn() => [
                'uuid' => $this->location->uuid,
                'name' => $this->location->name,
            ]),
            'updated_at' => $this->updated_at,
        ];
    }
}
