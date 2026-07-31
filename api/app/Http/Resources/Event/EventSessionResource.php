<?php

namespace App\Http\Resources\Event;

use Illuminate\Http\Resources\Json\JsonResource;

class EventSessionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'gate_opens_at' => $this->gate_opens_at,
            'capacity' => $this->capacity,
            'status' => $this->status,
            'sales_start_at' => $this->sales_start_at,
            'sales_end_at' => $this->sales_end_at,
            'event' => $this->whenLoaded('event', fn() => [
                'uuid' => $this->event->uuid,
                'name' => $this->event->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
