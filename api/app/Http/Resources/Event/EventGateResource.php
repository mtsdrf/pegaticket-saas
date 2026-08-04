<?php

namespace App\Http\Resources\Event;

use Illuminate\Http\Resources\Json\JsonResource;

class EventGateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'event' => $this->whenLoaded('event', fn () => [
                'uuid' => $this->event->uuid,
                'name' => $this->event->name,
            ]),
            'allowed_ticket_types' => $this->whenLoaded('allowedTicketTypes', fn () => $this->allowedTicketTypes->map(fn ($ticketType) => [
                'uuid' => $ticketType->uuid,
                'name' => $ticketType->name,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
