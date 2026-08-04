<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettlementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'status' => $this->status,
            'scheduled_for' => $this->scheduled_for,
            'released_at' => $this->released_at,
            'gross_amount' => (float) $this->gross_amount,
            'platform_fee_amount' => (float) $this->platform_fee_amount,
            'processor_fee_amount' => (float) $this->processor_fee_amount,
            'net_amount' => (float) $this->net_amount,
            'receivables_count' => (int) ($this->receivables_count ?? 0),
            'open_adjustments_amount' => (float) ($this->open_adjustments_amount ?? 0),
            'tenant' => $this->when($this->relationLoaded('tenant') && $this->tenant !== null, fn () => [
                'uuid' => $this->tenant->uuid,
                'name' => $this->tenant->name,
            ]),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
        ];
    }
}
