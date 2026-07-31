<?php

namespace App\DTOs\Stock;

class UpdateStockLocationDTO
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $type,
        public readonly ?string $address,
        public readonly ?bool $isDefault,
        public readonly ?bool $isActive,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            type: $data['type'] ?? null,
            address: $data['address'] ?? null,
            isDefault: array_key_exists('is_default', $data) ? filter_var($data['is_default'], FILTER_VALIDATE_BOOLEAN) : null,
            isActive: array_key_exists('is_active', $data) ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : null,
        );
    }
}
