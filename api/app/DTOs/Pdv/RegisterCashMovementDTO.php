<?php

namespace App\DTOs\Pdv;

class RegisterCashMovementDTO
{
    public function __construct(
        public readonly string $type,
        public readonly float $amount,
        public readonly ?string $reason,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            amount: (float) $data['amount'],
            reason: $data['reason'] ?? null,
        );
    }
}
