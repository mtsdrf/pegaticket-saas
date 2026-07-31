<?php

namespace App\DTOs\Balcao;

class UpdateStationDTO
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $type,
        public readonly ?bool $isActive,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            type: $data['type'] ?? null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
        );
    }
}
