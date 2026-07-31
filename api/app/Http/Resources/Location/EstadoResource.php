<?php

namespace App\Http\Resources\Location;

use Illuminate\Http\Resources\Json\JsonResource;

class EstadoResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'uf' => $this->uf,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
