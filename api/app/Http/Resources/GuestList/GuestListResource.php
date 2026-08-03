<?php

namespace App\Http\Resources\GuestList;

use Illuminate\Http\Resources\Json\JsonResource;

class GuestListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'quantity_per_entry' => $this->quantity_per_entry,
            'notes' => $this->notes,
            'entries_count' => $this->whenCounted('entries', $this->entries_count, fn () => $this->whenLoaded('entries', fn () => $this->entries->count())),
            'redeemed_entries_count' => $this->when(
                isset($this->redeemed_entries_count),
                $this->redeemed_entries_count,
                fn () => $this->whenLoaded('entries', fn () => $this->entries->whereNotNull('redeemed_at')->count())
            ),
            'event' => $this->whenLoaded('event', fn () => [
                'uuid' => $this->event->uuid,
                'name' => $this->event->name,
            ]),
            'session' => $this->whenLoaded('session', fn () => $this->session ? [
                'uuid' => $this->session->uuid,
                'name' => $this->session->name,
            ] : null),
            'ticket_type' => $this->whenLoaded('ticketType', fn () => [
                'uuid' => $this->ticketType->uuid,
                'name' => $this->ticketType->name,
            ]),
            'entries' => GuestListEntryResource::collection($this->whenLoaded('entries')),
            'created_at' => $this->created_at,
        ];
    }
}
