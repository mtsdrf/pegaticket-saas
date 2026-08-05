<?php

namespace App\Http\Resources\TicketTypeWaitlist;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypeWaitlistEntryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'quantity_desired' => $this->quantity_desired,
            'notified_at' => $this->notified_at,
            'created_at' => $this->created_at,
            'ticket_type' => $this->whenLoaded('ticketType', fn () => [
                'uuid' => $this->ticketType->uuid,
                'name' => $this->ticketType->name,
            ]),
        ];
    }
}
