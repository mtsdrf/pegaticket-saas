<?php

namespace App\DTOs\Auth;

class ConfirmEmailChangeDTO
{
    public function __construct(
        public readonly string $token,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            token: $data['token'],
        );
    }
}
