<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Resources\Json\JsonResource;

class InventoryHoldResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'origin' => $this->origin,
            'session_token' => $this->session_token,
            'expires_at' => $this->expires_at,
            'server_time' => now(),
            'remaining_seconds' => $this->expires_at?->isFuture() ? now()->diffInSeconds($this->expires_at, false) : 0,
            'event' => $this->whenLoaded('event', fn() => [
                'uuid' => $this->event->uuid,
                'name' => $this->event->name,
                'slug' => $this->event->slug,
            ]),
            'session' => $this->whenLoaded('session', fn() => $this->session ? [
                'uuid' => $this->session->uuid,
                'name' => $this->session->name,
                'starts_at' => $this->session->starts_at,
                'ends_at' => $this->session->ends_at,
            ] : null),
            'items' => $this->whenLoaded('items', fn() => $this->items->map(fn($item) => [
                'uuid' => $item->uuid,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price !== null ? (float) $item->unit_price : null,
                'ticket_type' => $item->relationLoaded('ticketType') && $item->ticketType ? [
                    'uuid' => $item->ticketType->uuid,
                    'name' => $item->ticketType->name,
                ] : null,
                'event_product' => $item->relationLoaded('eventProduct') && $item->eventProduct ? [
                    'uuid' => $item->eventProduct->uuid,
                    'name' => $item->eventProduct->name,
                ] : null,
                'seat' => $item->relationLoaded('seat') && $item->seat ? [
                    'uuid' => $item->seat->uuid,
                    'label' => $item->seat->label,
                    'sector_name' => $item->seat->sector_name,
                    'kind' => $item->seat->kind,
                ] : null,
                'ticket_batch' => $item->relationLoaded('ticketBatch') && $item->ticketBatch ? [
                    'uuid' => $item->ticketBatch->uuid,
                    'name' => $item->ticketBatch->name,
                    'price' => (float) $item->ticketBatch->price,
                ] : null,
                'meta' => $item->meta,
            ])->values()),
            'created_at' => $this->created_at,
        ];
    }
}
