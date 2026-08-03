<?php

namespace App\Http\Resources\GuestList;

use Illuminate\Http\Resources\Json\JsonResource;

/** Vitrine pública do convite (sem login) — GET /convites/{token}. */
class GuestInviteResource extends JsonResource
{
    public function toArray($request): array
    {
        $guestList = $this->guestList;

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'is_redeemed' => $this->redeemed_at !== null,
            'redeemed_at' => $this->redeemed_at,
            'quantity' => $guestList->quantity_per_entry,
            'event' => [
                'uuid' => $guestList->event->uuid,
                'name' => $guestList->event->name,
            ],
            'session' => $guestList->session ? [
                'uuid' => $guestList->session->uuid,
                'name' => $guestList->session->name,
                'starts_at' => $guestList->session->starts_at,
            ] : null,
            'ticket_type' => [
                'uuid' => $guestList->ticketType->uuid,
                'name' => $guestList->ticketType->name,
            ],
        ];
    }
}
