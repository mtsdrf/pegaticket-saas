<?php

namespace App\DTOs\Venue;

class CreateSeatDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $venueUuid,
        public readonly ?string $sectorName,
        public readonly string $label,
        public readonly string $kind,
        public readonly int $capacity,
        public readonly float $posX,
        public readonly float $posY,
        public readonly ?float $width,
        public readonly ?float $height,
        public readonly ?array $geometryPoints,
        public readonly bool $isAccessible,
        public readonly string $status,
    ) {
    }

    public static function fromArray(array $data, int $tenantId, string $venueUuid): self
    {
        return new self(
            tenantId: $tenantId,
            venueUuid: $venueUuid,
            sectorName: $data['sector_name'] ?? null,
            label: $data['label'],
            kind: $data['kind'] ?? 'assento',
            capacity: isset($data['capacity']) ? (int) $data['capacity'] : 1,
            posX: isset($data['pos_x']) ? (float) $data['pos_x'] : 0.0,
            posY: isset($data['pos_y']) ? (float) $data['pos_y'] : 0.0,
            width: isset($data['width']) ? (float) $data['width'] : null,
            height: isset($data['height']) ? (float) $data['height'] : null,
            geometryPoints: array_key_exists('geometry_points', $data) ? $data['geometry_points'] : null,
            isAccessible: (bool) ($data['is_accessible'] ?? false),
            status: $data['status'] ?? 'disponivel',
        );
    }
}
