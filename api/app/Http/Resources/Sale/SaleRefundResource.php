<?php

namespace App\Http\Resources\Sale;

use Illuminate\Http\Resources\Json\JsonResource;

class SaleRefundResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'refunded_at' => $this->refunded_at,
            'external_reference' => $this->external_reference,
            'has_receipt' => $this->receipt_path !== null,
            'notes' => $this->notes,
            'release_seats' => $this->release_seats,
            'status' => $this->status,
            'tickets' => $this->whenLoaded('tickets', fn() => $this->tickets->map(fn($ticket) => [
                'uuid' => $ticket->uuid,
                'code' => $ticket->code,
                'status' => $ticket->status,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
