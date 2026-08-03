<?php

namespace App\DTOs\Ticket;

class TransferTicketDTO
{
    public function __construct(
        public readonly string $attendeeName,
        public readonly ?string $attendeeDocument,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            attendeeName: $data['attendee_name'],
            attendeeDocument: $data['attendee_document'] ?? null,
        );
    }
}
