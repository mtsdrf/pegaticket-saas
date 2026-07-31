<?php

namespace App\Http\Resources\Location;

use Illuminate\Http\Resources\Json\JsonResource;

class BairroResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'cidade_uuid' => $this->cidade->uuid,
            'cidade_name' => $this->cidade->name,
            'created_at' => $this->created_at,
        ];
    }
}
