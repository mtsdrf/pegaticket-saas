<?php

namespace App\DTOs\Stock;

class CreateStockLocationDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $name,
        public readonly ?string $type,
        public readonly ?string $address,
        public readonly bool $isDefault,
        public readonly bool $isActive,
    ) {
    }

    public static function fromArray(array $data, int $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            name: $data['name'],
            type: $data['type'] ?? null,
            address: $data['address'] ?? null,
            isDefault: $data['is_default'] ?? false,
            isActive: $data['is_active'] ?? true,
        );
    }
}
