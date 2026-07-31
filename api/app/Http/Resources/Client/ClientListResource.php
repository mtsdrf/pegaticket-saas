<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class ClientListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'phone_primary' => $this->phone_primary,
            'is_active' => $this->is_active,
            'endereco' => $this->whenLoaded('endereco', fn() => [
                'cidade_name' => $this->endereco?->cidade?->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
