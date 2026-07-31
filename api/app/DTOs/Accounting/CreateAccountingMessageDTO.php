<?php

namespace App\DTOs\Accounting;

class CreateAccountingMessageDTO
{
    public function __construct(
        public readonly string $body,
        public readonly ?string $dueDate,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            body: $data['body'],
            dueDate: $data['due_date'] ?? null,
        );
    }
}
