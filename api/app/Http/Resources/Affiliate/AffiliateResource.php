<?php

namespace App\Http\Resources\Affiliate;

use Illuminate\Http\Resources\Json\JsonResource;

class AffiliateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'tracking_code' => $this->tracking_code,
            'commission_percentage' => $this->commission_percentage !== null ? (float) $this->commission_percentage : null,
            'status' => $this->status,
            'commissions_total_amount' => (float) ($this->commissions_total_amount ?? 0),
            'created_at' => $this->created_at,
        ];
    }
}
