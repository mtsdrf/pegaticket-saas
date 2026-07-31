<?php

namespace App\DTOs\Order;

class UpdateOrderInstallmentDTO
{
    public function __construct(
        public readonly int $installmentNumber,
        public readonly float $amount,
        public readonly string $dueDate,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            installmentNumber: (int) $data['installment_number'],
            amount: (float) $data['amount'],
            dueDate: $data['due_date'],
        );
    }
}
