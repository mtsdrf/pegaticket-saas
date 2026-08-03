<?php

namespace App\DTOs\CashSession;

class OpenCashSessionDTO
{
    public function __construct(
        public readonly float $openingAmount,
        public readonly ?string $openingNotes,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            openingAmount: (float) $data['opening_amount'],
            openingNotes: $data['opening_notes'] ?? null,
        );
    }
}
