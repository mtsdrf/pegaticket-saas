<?php

namespace App\DTOs\CashSession;

class CloseCashSessionDTO
{
    public function __construct(
        public readonly float $closingAmount,
        public readonly ?string $closingNotes,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            closingAmount: (float) $data['closing_amount'],
            closingNotes: $data['closing_notes'] ?? null,
        );
    }
}
