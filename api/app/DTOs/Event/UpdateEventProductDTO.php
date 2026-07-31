<?php

namespace App\DTOs\Event;

class UpdateEventProductDTO
{
    public function __construct(
        public readonly ?string $eventUuid,
        public readonly ?string $name,
        public readonly ?string $description,
        public readonly ?float $price,
        public readonly ?int $quantityAvailable,
        public readonly ?int $maxPerOrder,
        public readonly ?string $salesStartAt,
        public readonly ?string $salesEndAt,
        public readonly ?string $kind,
        public readonly ?bool $requiresPlate,
        public readonly ?bool $requiresModel,
        public readonly ?bool $requiresColor,
        public readonly ?string $status,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            eventUuid: $data['event_uuid'] ?? null,
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            price: isset($data['price']) ? (float) $data['price'] : null,
            quantityAvailable: array_key_exists('quantity_available', $data) ? ($data['quantity_available'] !== null ? (int) $data['quantity_available'] : null) : null,
            maxPerOrder: array_key_exists('max_per_order', $data) ? ($data['max_per_order'] !== null ? (int) $data['max_per_order'] : null) : null,
            salesStartAt: $data['sales_start_at'] ?? null,
            salesEndAt: $data['sales_end_at'] ?? null,
            kind: $data['kind'] ?? null,
            requiresPlate: array_key_exists('requires_plate', $data) ? filter_var($data['requires_plate'], FILTER_VALIDATE_BOOLEAN) : null,
            requiresModel: array_key_exists('requires_model', $data) ? filter_var($data['requires_model'], FILTER_VALIDATE_BOOLEAN) : null,
            requiresColor: array_key_exists('requires_color', $data) ? filter_var($data['requires_color'], FILTER_VALIDATE_BOOLEAN) : null,
            status: $data['status'] ?? null,
        );
    }
}
