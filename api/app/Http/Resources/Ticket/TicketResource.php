<?php

namespace App\Http\Resources\Ticket;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Uso de staff (tenant-scoped, perm:tickets,read) — inclui qr_token
 * deliberadamente (portaria/balcão pode precisar reimprimir/reexibir o QR
 * a partir do painel). Nunca exposto em contexto público sem autenticação.
 */
class TicketResource extends JsonResource
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
            'ticket_type' => $this->whenLoaded('ticketType', fn() => [
                'uuid' => $this->ticketType->uuid,
                'name' => $this->ticketType->name,
            ]),
            'event' => $this->whenLoaded('ticketType', fn() => $this->ticketType->relationLoaded('event') && $this->ticketType->event ? [
                'uuid' => $this->ticketType->event->uuid,
                'name' => $this->ticketType->event->name,
            ] : null),
            'session' => $this->whenLoaded('ticketType', fn() => $this->ticketType->relationLoaded('session') && $this->ticketType->session ? [
                'uuid' => $this->ticketType->session->uuid,
                'name' => $this->ticketType->session->name,
            ] : null),
            'seat' => $this->whenLoaded('seat', fn() => $this->seat ? [
                'uuid' => $this->seat->uuid,
                'label' => $this->seat->label,
                'sector_name' => $this->seat->sector_name,
            ] : null),
            'sale' => $this->whenLoaded('orderItem', fn() => $this->orderItem->relationLoaded('order') ? [
                'uuid' => $this->orderItem->order->uuid,
                'codigo' => $this->orderItem->order->codigo,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
