<?php

namespace App\Http\Resources\Portal;

use Illuminate\Http\Resources\Json\JsonResource;

class PortalLinkResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'tenant_name' => $this->whenLoaded('tenant', fn() => $this->tenant->name),
            'client_name' => $this->whenLoaded('client', fn() => $this->client->name),
            'confirmed_at' => $this->confirmed_at,
        ];
    }
}
