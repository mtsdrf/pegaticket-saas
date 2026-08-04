<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'currency' => $this->currency,
            'gross_amount' => (float) $this->gross_amount,
            'platform_fee_amount' => (float) $this->platform_fee_amount,
            'processor_fee_amount' => (float) $this->processor_fee_amount,
            'net_amount' => (float) $this->net_amount,
            'reserve_amount' => (float) $this->reserve_amount,
            'reserve_status' => $this->reserve_status,
            'reserve_release_at' => $this->reserve_release_at,
            'reserve_released_at' => $this->reserve_released_at,
            'available_at' => $this->available_at,
            'event_ends_at' => $this->event_ends_at,
            'provider' => $this->provider,
            'provider_charge_id' => $this->provider_charge_id,
            'provider_split_id' => $this->provider_split_id,
            'tenant' => $this->when($this->relationLoaded('tenant') && $this->tenant !== null, fn () => [
                'uuid' => $this->tenant->uuid,
                'name' => $this->tenant->name,
            ]),
            'sale' => $this->when($this->sale !== null, fn () => [
                'uuid' => $this->sale->uuid,
                'codigo' => $this->sale->codigo,
            ]),
            'event' => $this->when($this->event !== null, fn () => [
                'uuid' => $this->event->uuid,
                'name' => $this->event->name,
            ]),
            'settlement' => $this->when($this->settlement !== null, fn () => [
                'uuid' => $this->settlement->uuid,
                'code' => $this->settlement->code,
                'status' => $this->settlement->status,
                'scheduled_for' => $this->settlement->scheduled_for,
                'released_at' => $this->settlement->released_at,
            ]),
            'open_adjustments_amount' => (float) ($this->open_adjustments_amount ?? 0),
            'created_at' => $this->created_at,
        ];
    }
}
