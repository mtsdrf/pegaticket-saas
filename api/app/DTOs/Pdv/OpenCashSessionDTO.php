<?php

namespace App\DTOs\Pdv;

class OpenCashSessionDTO
{
    public function __construct(
        public readonly ?string $cashRegisterUuid,
        public readonly float $openingAmount,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            cashRegisterUuid: $data['cash_register_uuid'] ?? null,
            openingAmount: (float) ($data['opening_amount'] ?? 0.0),
        );
    }
}
