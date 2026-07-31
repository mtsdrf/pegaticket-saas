<?php

namespace App\DTOs\Accounting;

class ConfirmTotpDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $code,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            password: $data['password'],
            code: (string) $data['code'],
        );
    }
}
