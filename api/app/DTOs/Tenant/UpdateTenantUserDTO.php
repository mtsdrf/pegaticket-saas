<?php

namespace App\DTOs\Tenant;

class UpdateTenantUserDTO
{
    public function __construct(
        public readonly ?string $roleUuid,
        public readonly ?bool $isActive,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            roleUuid: $data['role_uuid'] ?? null,
            isActive: $data['is_active'] ?? null,
        );
    }
}