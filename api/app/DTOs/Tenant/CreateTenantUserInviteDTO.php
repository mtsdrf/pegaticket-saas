<?php

namespace App\DTOs\Tenant;

class CreateTenantUserInviteDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $roleUuid,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            roleUuid: $data['role_uuid'],
        );
    }
}
