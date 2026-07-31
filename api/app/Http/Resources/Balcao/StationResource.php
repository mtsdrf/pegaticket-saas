<?php

namespace App\Http\Resources\Balcao;

use Illuminate\Http\Resources\Json\JsonResource;

class StationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'type' => $this->type,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
