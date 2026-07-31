<?php

namespace App\DTOs\Client;

class UpdateDiaIdealDTO
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?bool $isActive,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            isActive: $data['is_active'] ?? null,
        );
    }
}
