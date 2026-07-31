<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Resources\Json\JsonResource;

class StoreDeliveryFeeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'fee' => (float) $this->fee,
            'bairro' => $this->whenLoaded('bairro', fn() => [
                'uuid' => $this->bairro->uuid,
                'name' => $this->bairro->name,
            ]),
        ];
    }
}
