<?php

namespace App\DTOs\Auth;

class RequestEmailChangeDTO
{
    public function __construct(
        public readonly string $newEmail,
        public readonly string $currentPassword,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            newEmail: $data['new_email'],
            currentPassword: $data['current_password'],
        );
    }
}
