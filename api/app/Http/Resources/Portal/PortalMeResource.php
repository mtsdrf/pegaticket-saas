<?php

namespace App\Http\Resources\Portal;

use Illuminate\Http\Resources\Json\JsonResource;

class PortalMeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'linked_stores' => $this->whenLoaded('links', fn() => $this->links->map(fn($link) => [
                'tenant_name' => $link->tenant->name,
                'confirmed_at' => $link->confirmed_at,
            ])),
        ];
    }
}
