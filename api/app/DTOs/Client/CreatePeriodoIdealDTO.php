<?php

namespace App\DTOs\Client;

class CreatePeriodoIdealDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $name,
        public readonly bool $isActive,
    ) {
    }

    public static function fromArray(array $data, int $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            name: $data['name'],
            isActive: $data['is_active'] ?? true,
        );
    }
}
