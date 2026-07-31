<?php

namespace App\DTOs\Report;

class CreateReceivableInteractionDTO
{
    public function __construct(
        public readonly ?string $installmentUuid,
        public readonly string $interactionType,
        public readonly ?string $channel,
        public readonly ?string $notes,
        public readonly ?float $promisedAmount,
        public readonly ?string $promisedDueDate,
        public readonly ?string $contactedAt,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            installmentUuid: $data['installment_uuid'] ?? null,
            interactionType: (string) $data['interaction_type'],
            channel: $data['channel'] ?? null,
            notes: $data['notes'] ?? null,
            promisedAmount: isset($data['promised_amount']) ? (float) $data['promised_amount'] : null,
            promisedDueDate: $data['promised_due_date'] ?? null,
            contactedAt: $data['contacted_at'] ?? null,
        );
    }
}
