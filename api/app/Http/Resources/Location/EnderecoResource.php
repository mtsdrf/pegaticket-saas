<?php

namespace App\Http\Resources\Location;

use Illuminate\Http\Resources\Json\JsonResource;

class EnderecoResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'logradouro' => $this->logradouro,
            'numero' => $this->numero,
            'complemento' => $this->complemento,
            'cep' => $this->cep,
            'is_active' => $this->is_active,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'geocode_status' => $this->geocode_status,
            'estado_uuid' => $this->estado->uuid,
            'estado_name' => $this->estado->name,
            'cidade_uuid' => $this->cidade->uuid,
            'cidade_name' => $this->cidade->name,
            'bairro_uuid' => $this->bairro->uuid,
            'bairro_name' => $this->bairro->name,
            'created_at' => $this->created_at,
        ];
    }
}
