<?php

namespace App\DTOs\Location;

class UpdateEnderecoDTO
{
    public function __construct(
        public readonly ?string $estadoUuid,
        public readonly ?string $cidadeUuid,
        public readonly ?string $bairroUuid,
        public readonly ?string $logradouro,
        public readonly ?string $numero,
        public readonly ?string $complemento,
        public readonly ?string $cep,
        public readonly ?bool $isActive,
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            estadoUuid: $data['estado_uuid'] ?? null,
            cidadeUuid: $data['cidade_uuid'] ?? null,
            bairroUuid: $data['bairro_uuid'] ?? null,
            logradouro: $data['logradouro'] ?? null,
            numero: $data['numero'] ?? null,
            complemento: $data['complemento'] ?? null,
            cep: $data['cep'] ?? null,
            isActive: $data['is_active'] ?? null,
            lat: isset($data['lat']) ? (float) $data['lat'] : null,
            lng: isset($data['lng']) ? (float) $data['lng'] : null,
        );
    }
}
