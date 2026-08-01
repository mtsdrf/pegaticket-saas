<?php

namespace App\DTOs\Sale;

class RequestSaleCancellationDTO
{
    public function __construct(
        public readonly ?string $reason,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            reason: $data['reason'] ?? null,
        );
    }
}
