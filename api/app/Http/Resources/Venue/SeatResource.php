<?php

namespace App\Http\Resources\Venue;

use Illuminate\Http\Resources\Json\JsonResource;

class SeatResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'sector_name' => $this->sector_name,
            'label' => $this->label,
            'kind' => $this->kind,
            'capacity' => $this->capacity,
            'pos_x' => (float) $this->pos_x,
            'pos_y' => (float) $this->pos_y,
            'is_accessible' => $this->is_accessible,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
