<?php

namespace App\DTOs\Stock;

class StockReserveCancelDTO
{
    public function __construct(
        public readonly string $movementUuid,
        public readonly ?string $notes,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            movementUuid: $data['movement_uuid'],
            notes: $data['notes'] ?? null,
        );
    }
}
