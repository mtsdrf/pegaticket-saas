<?php

namespace App\Http\Resources\Event;

use Illuminate\Http\Resources\Json\JsonResource;

class EventProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'quantity_available' => $this->quantity_available,
            'max_per_order' => $this->max_per_order,
            'sales_start_at' => $this->sales_start_at,
            'sales_end_at' => $this->sales_end_at,
            'kind' => $this->kind,
            'requires_plate' => $this->requires_plate,
            'requires_model' => $this->requires_model,
            'requires_color' => $this->requires_color,
            'status' => $this->status,
            'event' => $this->whenLoaded('event', fn() => [
                'uuid' => $this->event->uuid,
                'name' => $this->event->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
