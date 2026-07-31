<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'provider' => $this->provider,
            'provider_charge_id' => $this->provider_charge_id,
            'method' => $this->method,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
            'order' => $this->when($this->payable !== null, fn() => [
                'uuid' => $this->payable->uuid,
                'codigo' => $this->payable->codigo,
            ]),
            'refunds' => $this->refunds->map(fn($refund) => [
                'uuid' => $refund->uuid,
                'amount' => (float) $refund->amount,
                'type' => $refund->type,
                'status' => $refund->status,
                'protocol' => $refund->protocol,
                'reason' => $refund->reason,
            ])->values(),
            'webhook_event' => $this->when($this->matched_webhook_event !== null, fn() => [
                'provider' => $this->matched_webhook_event->provider,
                'external_id' => $this->matched_webhook_event->external_id,
                'processed_at' => $this->matched_webhook_event->processed_at,
            ]),
        ];
    }
}
