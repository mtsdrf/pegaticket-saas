<?php

namespace App\DTOs\Balcao;

class CreateTableDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $label,
        public readonly ?string $area,
        public readonly ?int $seats,
        public readonly string $status,
    ) {
    }

    public static function fromArray(array $data, int $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            label: $data['label'],
            area: $data['area'] ?? null,
            seats: isset($data['seats']) ? (int) $data['seats'] : null,
            status: $data['status'] ?? 'free',
        );
    }
}
