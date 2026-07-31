<?php

namespace App\Http\Resources\Location;

use Illuminate\Http\Resources\Json\JsonResource;

class CidadeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'estado_uuid' => $this->estado->uuid,
            'estado_name' => $this->estado->name,
            'created_at' => $this->created_at,
        ];
    }
}
