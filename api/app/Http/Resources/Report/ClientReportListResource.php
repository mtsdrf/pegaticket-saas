<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Resources\Json\JsonResource;

class ClientReportListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'phone_primary' => $this->phone_primary,
            'phone_secondary' => $this->phone_secondary,
            'endereco' => $this->endereco ? [
                'cidade_name' => $this->endereco->cidade?->name,
                'bairro_name' => $this->endereco->bairro?->name,
            ] : null,
            'orders_count' => (int) ($this->orders_count ?? 0),
        ];
    }
}
