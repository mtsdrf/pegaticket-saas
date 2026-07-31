<?php

namespace App\DTOs\Product;

class UpdateProductTypeDTO
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?int $priority,
        public readonly ?bool $isActive,
        public readonly ?string $productCategoryUuid,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            priority: $data['priority'] ?? null,
            isActive: $data['is_active'] ?? null,
            productCategoryUuid: $data['product_category_uuid'] ?? null,
        );
    }
}
