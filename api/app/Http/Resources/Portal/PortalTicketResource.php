<?php

namespace App\Http\Resources\Portal;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * "Meus ingressos" (comprador via Portal). Inclui qr_token de propósito —
 * é exatamente o que a tela precisa renderizar como QR Code. Posse já
 * verificada antes de chegar aqui (PortalCustomerService::findOwnedOrder).
 */
class PortalTicketResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'qr_token' => $this->qr_token,
            'status' => $this->status,
            'attendee_name' => $this->attendee_name,
            'attendee_document' => $this->attendee_document,
            'issued_at' => $this->issued_at,
            'ticket_type' => $this->whenLoaded('ticketType', fn () => [
                'uuid' => $this->ticketType->uuid,
                'name' => $this->ticketType->name,
            ]),
            'event' => $this->whenLoaded('ticketType', fn () => $this->ticketType->relationLoaded('event') && $this->ticketType->event ? [
                'uuid' => $this->ticketType->event->uuid,
                'name' => $this->ticketType->event->name,
            ] : null),
            'session' => $this->whenLoaded('ticketType', fn () => $this->ticketType->relationLoaded('session') && $this->ticketType->session ? [
                'uuid' => $this->ticketType->session->uuid,
                'name' => $this->ticketType->session->name,
                'starts_at' => $this->ticketType->session->starts_at,
            ] : null),
            'seat' => $this->whenLoaded('seat', fn () => $this->seat ? [
                'label' => $this->seat->label,
                'sector_name' => $this->seat->sector_name,
            ] : null),
        ];
    }
}
