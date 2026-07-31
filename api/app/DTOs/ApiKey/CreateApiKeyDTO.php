<?php

namespace App\DTOs\ApiKey;

class CreateApiKeyDTO
{
    public function __construct(
        public readonly string $name,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
        );
    }
}
