<?php

namespace App\DTOs\TicketTypeWaitlist;

class CreateTicketTypeWaitlistEntryDTO
{
    public function __construct(
        public readonly string $ticketTypeUuid,
        public readonly string $name,
        public readonly string $email,
        public readonly int $quantityDesired,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            ticketTypeUuid: $data['ticket_type_uuid'],
            name: $data['name'],
            email: $data['email'],
            quantityDesired: max(1, (int) ($data['quantity_desired'] ?? 1)),
        );
    }
}
