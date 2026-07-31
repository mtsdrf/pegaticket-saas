<?php

namespace App\DTOs\Portal;

class RequestOtpDTO
{
    public function __construct(
        public readonly string $email,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
        );
    }
}
