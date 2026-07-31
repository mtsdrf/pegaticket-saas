<?php

namespace App\DTOs\Group;

class UpdateGroupDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $slug = null,
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
            isActive: $data['is_active'] ?? null
        );
    }
}