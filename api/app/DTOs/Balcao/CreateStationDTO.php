<?php

namespace App\DTOs\Balcao;

class CreateStationDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $name,
        public readonly string $type,
        public readonly bool $isActive,
    ) {
    }

    public static function fromArray(array $data, int $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            name: $data['name'],
            type: $data['type'] ?? 'kitchen',
            isActive: $data['is_active'] ?? true,
        );
    }
}
