<?php

namespace App\DTOs\Tenant;

class UpdateTenantRoleDTO
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $slug,
        public readonly ?bool $isActive,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            slug: $data['slug'] ?? null,
            isActive: $data['is_active'] ?? null,
        );
    }
}