<?php

namespace App\DTOs\Auth;

class ResetPasswordDTO
{
    public function __construct(
        public readonly string $token,
        public readonly string $newPassword,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            token: $data['token'],
            newPassword: $data['password'],
        );
    }
}
