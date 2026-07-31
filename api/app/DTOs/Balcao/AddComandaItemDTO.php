<?php

namespace App\DTOs\Balcao;

class AddComandaItemDTO
{
    public function __construct(
        public readonly string $productUuid,
        public readonly float $qty,
        public readonly ?string $notes,
        public readonly ?string $clientItemUuid,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            productUuid: $data['product_uuid'],
            qty: (float) $data['qty'],
            notes: $data['notes'] ?? null,
            clientItemUuid: $data['client_item_uuid'] ?? null,
        );
    }
}
