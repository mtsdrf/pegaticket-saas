<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettlementAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'reason' => $this->reason,
            'status' => $this->status,
            'resolution_type' => $this->resolution_type,
            'resolution_notes' => $this->resolution_notes,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
            'metadata' => $this->metadata,
            'tenant' => $this->when($this->relationLoaded('tenant') && $this->tenant !== null, fn () => [
                'uuid' => $this->tenant->uuid,
                'name' => $this->tenant->name,
            ]),
            'sale' => $this->when($this->sale !== null, fn () => [
                'uuid' => $this->sale->uuid,
                'codigo' => $this->sale->codigo,
            ]),
            'receivable' => $this->when($this->receivable !== null, fn () => [
                'uuid' => $this->receivable->uuid,
                'status' => $this->receivable->status,
                'net_amount' => (float) $this->receivable->net_amount,
                'provider_split_id' => $this->receivable->provider_split_id,
            ]),
            'settlement' => $this->when($this->settlement !== null, fn () => [
                'uuid' => $this->settlement->uuid,
                'code' => $this->settlement->code,
                'status' => $this->settlement->status,
                'scheduled_for' => $this->settlement->scheduled_for,
                'released_at' => $this->settlement->released_at,
                'net_amount' => (float) $this->settlement->net_amount,
            ]),
        ];
    }
}
