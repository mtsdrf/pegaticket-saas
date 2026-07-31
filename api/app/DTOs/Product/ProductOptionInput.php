<?php

namespace App\DTOs\Product;

class ProductOptionInput
{
    public function __construct(
        public readonly ?string $uuid,
        public readonly string $name,
        public readonly ?string $description,
        public readonly float $price,
        public readonly int $sortOrder,
        public readonly bool $isAvailable,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uuid: isset($data['uuid']) ? (string) $data['uuid'] : null,
            name: trim((string) $data['name']),
            description: isset($data['description']) && trim((string) $data['description']) !== '' ? trim((string) $data['description']) : null,
            price: (float) ($data['price'] ?? 0),
            sortOrder: (int) ($data['sort_order'] ?? 0),
            isAvailable: array_key_exists('is_available', $data) ? filter_var($data['is_available'], FILTER_VALIDATE_BOOLEAN) : true,
        );
    }
}
