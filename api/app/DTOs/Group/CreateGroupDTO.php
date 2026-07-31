<?php

namespace App\DTOs\Group;

class CreateGroupDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly bool $isActive
    )
    {
        //
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            slug: $data['slug'],
            isActive: $data['is_active'] ?? true
        );
    }
}