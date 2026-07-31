<?php

namespace App\DTOs\Location;

class CreateBairroDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $cidadeUuid,
        public readonly bool $isActive,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            cidadeUuid: $data['cidade_uuid'],
            isActive: $data['is_active'] ?? true,
        );
    }
}
