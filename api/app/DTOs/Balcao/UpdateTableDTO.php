<?php

namespace App\DTOs\Balcao;

class UpdateTableDTO
{
    public function __construct(
        public readonly ?string $label,
        public readonly ?string $area,
        public readonly ?int $seats,
        public readonly ?string $status,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            label: $data['label'] ?? null,
            area: array_key_exists('area', $data) ? $data['area'] : null,
            seats: array_key_exists('seats', $data) ? (isset($data['seats']) ? (int) $data['seats'] : null) : null,
            status: $data['status'] ?? null,
        );
    }
}
