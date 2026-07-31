<?php

namespace App\DTOs\Event;

class CreateEventProductDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $eventUuid,
        public readonly string $name,
        public readonly ?string $description,
        public readonly float $price,
        public readonly ?int $quantityAvailable,
        public readonly ?int $maxPerOrder,
        public readonly ?string $salesStartAt,
        public readonly ?string $salesEndAt,
        public readonly string $kind,
        public readonly bool $requiresPlate,
        public readonly bool $requiresModel,
        public readonly bool $requiresColor,
        public readonly string $status,
    ) {
    }

    public static function fromArray(array $data, int $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            eventUuid: $data['event_uuid'],
            name: $data['name'],
            description: $data['description'] ?? null,
            price: (float) $data['price'],
            quantityAvailable: isset($data['quantity_available']) ? (int) $data['quantity_available'] : null,
            maxPerOrder: isset($data['max_per_order']) ? (int) $data['max_per_order'] : null,
            salesStartAt: $data['sales_start_at'] ?? null,
            salesEndAt: $data['sales_end_at'] ?? null,
            kind: $data['kind'] ?? 'addon',
            requiresPlate: (bool) ($data['requires_plate'] ?? false),
            requiresModel: (bool) ($data['requires_model'] ?? false),
            requiresColor: (bool) ($data['requires_color'] ?? false),
            status: $data['status'] ?? 'rascunho',
        );
    }
}
