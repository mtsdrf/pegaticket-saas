<?php

namespace App\Http\Resources\Event;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypeChannelQuotaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'channel' => $this->channel,
            'quota' => $this->quota,
            'ticket_type' => $this->whenLoaded('ticketType', fn () => [
                'uuid' => $this->ticketType->uuid,
                'name' => $this->ticketType->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
