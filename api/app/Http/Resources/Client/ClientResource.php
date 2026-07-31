<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'cpf_cnpj' => $this->cpf_cnpj,
            'ie' => $this->ie,
            'ie_indicator' => $this->ie_indicator,
            'phone_primary' => $this->phone_primary,
            'phone_secondary' => $this->phone_secondary,
            'notes' => $this->notes,
            'is_trusted' => $this->is_trusted,
            'is_active' => $this->is_active,
            // endereco pode ser null: cliente "Consumidor Final" avulso do PDV
            // (venda de balcão sem cadastro) não tem endereço — ver
            // architecture-decisions.md.
            'endereco' => $this->endereco ? [
                'uuid' => $this->endereco->uuid,
                'logradouro' => $this->endereco->logradouro,
                'numero' => $this->endereco->numero,
                'complemento' => $this->endereco->complemento,
                'cep' => $this->endereco->cep,
                'estado_uuid' => $this->endereco->estado->uuid,
                'estado_name' => $this->endereco->estado->name,
                'cidade_uuid' => $this->endereco->cidade->uuid,
                'cidade_name' => $this->endereco->cidade->name,
                'bairro_uuid' => $this->endereco->bairro->uuid,
                'bairro_name' => $this->endereco->bairro->name,
                'lat' => $this->endereco->lat !== null ? (float) $this->endereco->lat : null,
                'lng' => $this->endereco->lng !== null ? (float) $this->endereco->lng : null,
                'geocode_status' => $this->endereco->geocode_status,
            ] : null,
            'created_at' => $this->created_at,
        ];
    }
}
