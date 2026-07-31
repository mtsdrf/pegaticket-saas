<?php

namespace App\DTOs\Location;

class CreateCidadeDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $estadoUuid,
        public readonly bool $isActive,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            estadoUuid: $data['estado_uuid'],
            isActive: $data['is_active'] ?? true,
        );
    }
}
