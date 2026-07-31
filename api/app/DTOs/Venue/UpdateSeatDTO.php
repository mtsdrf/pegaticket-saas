<?php

namespace App\DTOs\Venue;

class UpdateSeatDTO
{
    public function __construct(
        public readonly ?string $sectorName,
        public readonly ?string $label,
        public readonly ?string $kind,
        public readonly ?int $capacity,
        public readonly ?float $posX,
        public readonly ?float $posY,
        public readonly ?bool $isAccessible,
        public readonly ?string $status,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            sectorName: $data['sector_name'] ?? null,
            label: $data['label'] ?? null,
            kind: $data['kind'] ?? null,
            capacity: isset($data['capacity']) ? (int) $data['capacity'] : null,
            posX: isset($data['pos_x']) ? (float) $data['pos_x'] : null,
            posY: isset($data['pos_y']) ? (float) $data['pos_y'] : null,
            isAccessible: array_key_exists('is_accessible', $data) ? (bool) $data['is_accessible'] : null,
            status: $data['status'] ?? null,
        );
    }
}
