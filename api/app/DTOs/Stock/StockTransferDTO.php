<?php

namespace App\DTOs\Stock;

class StockTransferDTO
{
    public function __construct(
        public readonly string $productUuid,
        public readonly string $locationUuid,
        public readonly string $destinationLocationUuid,
        public readonly float $quantity,
        public readonly string $reason,
        public readonly ?string $notes,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            productUuid: $data['product_uuid'],
            locationUuid: $data['location_uuid'],
            destinationLocationUuid: $data['destination_location_uuid'],
            quantity: (float) $data['quantity'],
            reason: $data['reason'],
            notes: $data['notes'] ?? null,
        );
    }
}
