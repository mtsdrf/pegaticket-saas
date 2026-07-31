<?php

namespace App\DTOs\Functionality;

class UpdateFunctionalityDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $slug = null,
        public readonly ?string $description,
        public readonly ?bool $isActive = null
    )
    {
        //
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            slug: $data['slug'] ?? null,
            description: $data['description'] ?? null,
            isActive: $data['is_active'] ?? null
        );
    }
}