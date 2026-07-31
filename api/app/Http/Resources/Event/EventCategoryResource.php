<?php

namespace App\Http\Resources\Event;

use Illuminate\Http\Resources\Json\JsonResource;

class EventCategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'priority' => $this->priority,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
