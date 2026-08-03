<?php

namespace App\DTOs\GuestList;

class CreateGuestListDTO
{
    public function __construct(
        public readonly string $eventUuid,
        public readonly ?string $eventSessionUuid,
        public readonly string $ticketTypeUuid,
        public readonly string $name,
        public readonly int $quantityPerEntry,
        public readonly ?string $notes,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            eventUuid: $data['event_uuid'],
            eventSessionUuid: $data['event_session_uuid'] ?? null,
            ticketTypeUuid: $data['ticket_type_uuid'],
            name: $data['name'],
            quantityPerEntry: (int) ($data['quantity_per_entry'] ?? 1),
            notes: $data['notes'] ?? null,
        );
    }
}
