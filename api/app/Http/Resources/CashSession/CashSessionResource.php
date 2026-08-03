<?php

namespace App\Http\Resources\CashSession;

use Illuminate\Http\Resources\Json\JsonResource;

class CashSessionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'opening_amount' => $this->opening_amount,
            'closing_amount' => $this->closing_amount,
            'expected_cash_amount' => $this->expected_cash_amount,
            'difference_amount' => $this->difference_amount,
            'opening_notes' => $this->opening_notes,
            'closing_notes' => $this->closing_notes,
            'opened_at' => $this->opened_at,
            'closed_at' => $this->closed_at,
            'opened_by_name' => $this->whenLoaded('openedByUser', fn () => $this->openedByUser?->name),
            'closed_by_name' => $this->whenLoaded('closedByUser', fn () => $this->closedByUser?->name),
        ];
    }
}
