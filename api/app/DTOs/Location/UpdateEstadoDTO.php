<?php

namespace App\DTOs\Location;

class UpdateEstadoDTO
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $uf,
        public readonly ?bool $isActive,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            uf: isset($data['uf']) ? strtoupper($data['uf']) : null,
            isActive: $data['is_active'] ?? null,
        );
    }
}
