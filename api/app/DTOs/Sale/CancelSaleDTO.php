<?php

namespace App\DTOs\Sale;

class CancelSaleDTO
{
    public function __construct(
        public readonly string $cancellationReason,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            cancellationReason: $data['cancellation_reason'],
        );
    }
}
