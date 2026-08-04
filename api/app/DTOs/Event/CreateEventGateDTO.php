<?php

namespace App\DTOs\Event;

class CreateEventGateDTO
{
    /**
     * @param  string[]|null  $ticketTypeUuids
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly string $eventUuid,
        public readonly string $name,
        public readonly bool $isActive,
        public readonly ?array $ticketTypeUuids,
    ) {}

    public static function fromArray(array $data, int $tenantId, string $eventUuid): self
    {
        return new self(
            tenantId: $tenantId,
            eventUuid: $eventUuid,
            name: $data['name'],
            isActive: (bool) ($data['is_active'] ?? true),
            ticketTypeUuids: $data['ticket_type_uuids'] ?? null,
        );
    }
}
