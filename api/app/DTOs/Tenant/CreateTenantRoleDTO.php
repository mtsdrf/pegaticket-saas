<?php

namespace App\DTOs\Tenant;

class CreateTenantRoleDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $name,
        public readonly string $slug,
        public readonly bool $isActive,
    ) {
    }

    public static function fromArray(array $data, int $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            name: $data['name'],
            slug: $data['slug'],
            isActive: $data['is_active'] ?? true,
        );
    }
}