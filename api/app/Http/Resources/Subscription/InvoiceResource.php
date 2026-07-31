<?php

namespace App\Http\Resources\Subscription;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'competence_period' => $this->competence_period,
            'due_date' => $this->due_date?->toDateString(),
            'amount_gross' => (float) $this->amount_gross,
            'discount_amount' => (float) $this->discount_amount,
            'amount_net' => (float) $this->amount_net,
            'status' => $this->status,
            'dispute_deadline_at' => $this->dispute_deadline_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
