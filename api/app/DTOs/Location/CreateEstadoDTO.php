<?php

namespace App\DTOs\Location;

class CreateEstadoDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $uf,
        public readonly bool $isActive,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            uf: strtoupper($data['uf']),
            isActive: $data['is_active'] ?? true,
        );
    }
}
