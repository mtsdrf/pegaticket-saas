<?php

namespace App\DTOs\GuestList;

class UpdateGuestListDTO
{
    public function __construct(
        public readonly ?string $eventUuid,
        public readonly bool $eventUuidProvided,
        public readonly ?string $eventSessionUuid,
        public readonly bool $eventSessionUuidProvided,
        public readonly ?string $ticketTypeUuid,
        public readonly bool $ticketTypeUuidProvided,
        public readonly ?string $name,
        public readonly ?int $quantityPerEntry,
        public readonly ?string $notes,
        public readonly bool $notesProvided,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            eventUuid: $data['event_uuid'] ?? null,
            eventUuidProvided: array_key_exists('event_uuid', $data),
            eventSessionUuid: $data['event_session_uuid'] ?? null,
            eventSessionUuidProvided: array_key_exists('event_session_uuid', $data),
            ticketTypeUuid: $data['ticket_type_uuid'] ?? null,
            ticketTypeUuidProvided: array_key_exists('ticket_type_uuid', $data),
            name: $data['name'] ?? null,
            quantityPerEntry: array_key_exists('quantity_per_entry', $data) ? (int) $data['quantity_per_entry'] : null,
            notes: $data['notes'] ?? null,
            notesProvided: array_key_exists('notes', $data),
        );
    }
}
