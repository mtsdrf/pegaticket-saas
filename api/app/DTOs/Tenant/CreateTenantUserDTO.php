<?php

namespace App\DTOs\Tenant;

class CreateTenantUserDTO
{
    public function __construct(
        public readonly ?string $userUuid,
        public readonly string $tenantUuid,
        public readonly string $roleUuid,
        public readonly bool $isActive = true,
        public readonly ?string $userName = null,
        public readonly ?string $userEmail = null,
        public readonly ?string $userPassword = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            userUuid: $data['user_uuid'] ?? null,
            tenantUuid: $data['tenant_uuid'],
            roleUuid: $data['role_uuid'],
            isActive: $data['is_active'] ?? true,
            userName: $data['user']['name'] ?? null,
            userEmail: $data['user']['email'] ?? null,
            userPassword: $data['user']['password'] ?? null,
        );
    }
}
