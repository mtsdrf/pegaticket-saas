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
            'width' => $this->width !== null ? (float) $this->width : null,
            'height' => $this->height !== null ? (float) $this->height : null,
            'geometry_points' => collect($this->geometry_points ?? [])
                ->map(fn ($point) => [
                    'x' => isset($point['x']) ? (float) $point['x'] : 0.0,
                    'y' => isset($point['y']) ? (float) $point['y'] : 0.0,
                ])
                ->values()
                ->all(),
            'is_accessible' => $this->is_accessible,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
