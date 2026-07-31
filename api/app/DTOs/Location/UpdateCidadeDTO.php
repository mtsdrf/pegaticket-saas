<?php

namespace App\DTOs\Location;

class UpdateCidadeDTO
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $estadoUuid,
        public readonly ?bool $isActive,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            estadoUuid: $data['estado_uuid'] ?? null,
            isActive: $data['is_active'] ?? null,
        );
    }
}
