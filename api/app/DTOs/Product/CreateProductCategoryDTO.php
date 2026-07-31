<?php

namespace App\DTOs\Product;

class CreateProductCategoryDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $name,
        public readonly ?int $priority,
        public readonly bool $isActive,
    ) {
    }

    public static function fromArray(array $data, int $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            name: $data['name'],
            priority: $data['priority'] ?? null,
            isActive: $data['is_active'] ?? true,
        );
    }
}
