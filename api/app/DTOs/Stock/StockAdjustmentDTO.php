<?php

namespace App\DTOs\Stock;

class StockAdjustmentDTO
{
    public function __construct(
        public readonly string $productUuid,
        public readonly string $locationUuid,
        public readonly float $quantity,
        public readonly string $direction,
        public readonly string $reason,
        public readonly ?string $notes,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            productUuid: $data['product_uuid'],
            locationUuid: $data['location_uuid'],
            quantity: (float) $data['quantity'],
            direction: $data['direction'],
            reason: $data['reason'],
            notes: $data['notes'] ?? null,
        );
    }
}
