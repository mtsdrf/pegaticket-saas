<?php

namespace App\Http\Resources\Venue;

use Illuminate\Http\Resources\Json\JsonResource;

class VenueMapVersionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'version_number' => $this->version_number,
            'is_published' => $this->is_published,
            'created_at' => $this->created_at,
        ];
    }
}
