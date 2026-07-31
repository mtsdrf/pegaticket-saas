<?php

namespace App\Http\Resources\Subscription;

use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'reason' => $this->reason,
            'amount' => (float) $this->amount,
            'type' => $this->type,
            'protocol' => $this->protocol,
            'provider_refund_id' => $this->provider_refund_id,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
