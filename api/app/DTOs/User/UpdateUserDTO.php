<?php

namespace App\DTOs\User;

class UpdateUserDTO
{
    /**
     * @param string[]|null $groupUuids
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?string $password = null,
        public readonly ?bool $isActive = null,
        public readonly ?array $groupUuids = null
    )
    {
        //
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            password: $data['password'] ?? null,
            isActive: $data['is_active'] ?? null,
            groupUuids: $data['group_uuids'] ?? null
        );
    }
}