<?php

namespace App\Http\Resources\Balcao;

use Illuminate\Http\Resources\Json\JsonResource;

class TableResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'label' => $this->label,
            'area' => $this->area,
            'seats' => $this->seats,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
