<?php

namespace App\Http\Resources\Portal;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketResaleListingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'original_unit_price' => $this->original_unit_price,
            'asking_price' => $this->asking_price,
            'seller_payout_amount' => $this->seller_payout_amount,
            'seller_payout_status' => $this->seller_payout_status,
            'sold_at' => $this->sold_at,
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
            'ticket' => $this->whenLoaded('ticket', fn () => [
                'uuid' => $this->ticket->uuid,
                'status' => $this->ticket->status,
                'ticket_type' => $this->ticket->relationLoaded('ticketType') ? [
                    'uuid' => $this->ticket->ticketType->uuid,
                    'name' => $this->ticket->ticketType->name,
                ] : null,
                'event' => $this->ticket->relationLoaded('ticketType') && $this->ticket->ticketType->relationLoaded('event') && $this->ticket->ticketType->event ? [
                    'uuid' => $this->ticket->ticketType->event->uuid,
                    'name' => $this->ticket->ticketType->event->name,
                ] : null,
                'session' => $this->ticket->relationLoaded('ticketType') && $this->ticket->ticketType->relationLoaded('session') && $this->ticket->ticketType->session ? [
                    'uuid' => $this->ticket->ticketType->session->uuid,
                    'name' => $this->ticket->ticketType->session->name,
                    'starts_at' => $this->ticket->ticketType->session->starts_at,
                ] : null,
            ]),
            'seller' => $this->whenLoaded('seller', fn () => $this->seller ? [
                'uuid' => $this->seller->uuid,
                'name' => $this->seller->name,
            ] : null),
            'buyer' => $this->whenLoaded('buyer', fn () => $this->buyer ? [
                'uuid' => $this->buyer->uuid,
                'name' => $this->buyer->name,
            ] : null),
        ];
    }
}
