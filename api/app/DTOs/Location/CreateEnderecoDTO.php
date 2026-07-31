<?php

namespace App\DTOs\Location;

class CreateEnderecoDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $estadoUuid,
        public readonly string $cidadeUuid,
        public readonly string $bairroUuid,
        public readonly string $logradouro,
        public readonly ?string $numero,
        public readonly ?string $complemento,
        public readonly ?string $cep,
        public readonly bool $isActive,
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
    ) {
    }

    public static function fromArray(array $data, int $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            estadoUuid: $data['estado_uuid'],
            cidadeUuid: $data['cidade_uuid'],
            bairroUuid: $data['bairro_uuid'],
            logradouro: $data['logradouro'],
            numero: $data['numero'] ?? null,
            complemento: $data['complemento'] ?? null,
            cep: $data['cep'] ?? null,
            isActive: $data['is_active'] ?? true,
            lat: isset($data['lat']) ? (float) $data['lat'] : null,
            lng: isset($data['lng']) ? (float) $data['lng'] : null,
        );
    }
}
