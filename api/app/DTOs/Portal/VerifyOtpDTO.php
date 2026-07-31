<?php

namespace App\DTOs\Portal;

class VerifyOtpDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $code,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            code: $data['code'],
        );
    }
}
