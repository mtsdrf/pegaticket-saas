<?php

namespace App\DTOs\Location;

class UpdateBairroDTO
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $cidadeUuid,
        public readonly ?bool $isActive,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            cidadeUuid: $data['cidade_uuid'] ?? null,
            isActive: $data['is_active'] ?? null,
        );
    }
}
