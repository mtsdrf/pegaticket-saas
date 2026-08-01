<?php

namespace App\DTOs\Sale;

class ReallocateSaleInstallmentsDTO
{
    /**
     * @param array<int, array{uuid?: string, installment_number: int, amount: float, due_date: string}> $installments
     */
    public function __construct(
        public readonly array $installments,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            installments: $data['installments'],
        );
    }
}
