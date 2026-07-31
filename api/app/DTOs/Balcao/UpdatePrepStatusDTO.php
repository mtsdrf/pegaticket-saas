<?php

namespace App\DTOs\Balcao;

class UpdatePrepStatusDTO
{
    public function __construct(
        public readonly string $prepStatus,
        public readonly ?string $cancelledReason,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            prepStatus: $data['prep_status'],
            cancelledReason: $data['cancelled_reason'] ?? null,
        );
    }
}
