<?php

namespace App\DTOs\User;

class CreateUserDTO
{
    /**
     * @param string[] $groupUuids
     */
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly bool $isActive,
        public readonly array $groupUuids = []
    )
    {
        //
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
            isActive: $data['is_active'] ?? true,
            groupUuids: $data['group_uuids'] ?? []
        );
    }
}