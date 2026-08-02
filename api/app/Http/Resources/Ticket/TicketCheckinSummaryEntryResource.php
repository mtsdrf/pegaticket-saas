<?php

namespace App\Http\Resources\Ticket;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketCheckinSummaryEntryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'gate_name' => $this->gate_name,
            'result' => $this->result,
            'access_type' => $this->access_type,
            'reason' => $this->reason,
            'checked_in_at' => $this->checked_in_at,
            'operator' => $this->whenLoaded('operator', fn () => $this->operator ? [
                'uuid' => $this->operator->uuid,
                'name' => $this->operator->name,
            ] : null),
            'ticket' => $this->whenLoaded('ticket', fn () => $this->ticket ? [
                'uuid' => $this->ticket->uuid,
                'code' => $this->ticket->code,
                'attendee_name' => $this->ticket->attendee_name,
                'event' => $this->ticket->ticketType?->event ? [
                    'uuid' => $this->ticket->ticketType->event->uuid,
                    'name' => $this->ticket->ticketType->event->name,
                ] : null,
                'session' => $this->ticket->ticketType?->session ? [
                    'uuid' => $this->ticket->ticketType->session->uuid,
                    'name' => $this->ticket->ticketType->session->name,
                ] : null,
            ] : null),
        ];
    }
}
