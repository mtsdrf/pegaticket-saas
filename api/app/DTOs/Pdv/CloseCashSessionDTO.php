<?php

namespace App\DTOs\Pdv;

class CloseCashSessionDTO
{
    public function __construct(
        public readonly float $closingAmountDeclared,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            closingAmountDeclared: (float) ($data['closing_amount_declared'] ?? 0.0),
        );
    }
}
