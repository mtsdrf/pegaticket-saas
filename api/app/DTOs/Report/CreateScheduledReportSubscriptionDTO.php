<?php

namespace App\DTOs\Report;

class CreateScheduledReportSubscriptionDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $recipientEmail,
        public readonly string $frequency,
    ) {}

    public static function fromArray(array $data, int $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            recipientEmail: $data['recipient_email'],
            frequency: $data['frequency'],
        );
    }
}
