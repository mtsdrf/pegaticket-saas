<?php

namespace App\Http\Resources\Event;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketBatchResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'price' => (float) $this->price,
            'quantity' => $this->quantity,
            'quantity_sold' => $this->quantity_sold,
            'quantity_available' => max(0, $this->quantity - $this->quantity_sold),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'priority' => $this->priority,
            'auto_advance' => $this->auto_advance,
            'status' => $this->status,
            'ticket_type' => $this->whenLoaded('ticketType', fn() => [
                'uuid' => $this->ticketType->uuid,
                'name' => $this->ticketType->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
