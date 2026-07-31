<?php

namespace App\Http\Resources\Pdv;

use Illuminate\Http\Resources\Json\JsonResource;

class CashSessionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'opening_amount' => (float) $this->opening_amount,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closing_amount_declared' => $this->closing_amount_declared !== null ? (float) $this->closing_amount_declared : null,
            'closing_amount_expected' => $this->closing_amount_expected !== null ? (float) $this->closing_amount_expected : null,
            'difference' => $this->difference !== null ? (float) $this->difference : null,
            'cash_register' => new CashRegisterResource($this->whenLoaded('cashRegister')),
            'movements' => CashMovementResource::collection($this->whenLoaded('movements')),
        ];
    }
}
