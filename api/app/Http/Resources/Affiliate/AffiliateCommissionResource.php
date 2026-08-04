<?php

namespace App\Http\Resources\Affiliate;

use Illuminate\Http\Resources\Json\JsonResource;

class AffiliateCommissionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'sale_uuid' => $this->whenLoaded('sale', fn () => $this->sale->uuid),
            'sale_amount' => (float) $this->sale_amount,
            'percentage_applied' => (float) $this->percentage_applied,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
