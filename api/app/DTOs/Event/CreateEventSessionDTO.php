<?php

namespace App\DTOs\Event;

class CreateEventSessionDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $eventUuid,
        public readonly ?string $name,
        public readonly string $startsAt,
        public readonly string $endsAt,
        public readonly ?string $gateOpensAt,
        public readonly ?int $capacity,
        public readonly string $status,
        public readonly ?string $salesStartAt,
        public readonly ?string $salesEndAt,
    ) {
    }

    public static function fromArray(array $data, int $tenantId, string $eventUuid): self
    {
        return new self(
            tenantId: $tenantId,
            eventUuid: $eventUuid,
            name: $data['name'] ?? null,
            startsAt: $data['starts_at'],
            endsAt: $data['ends_at'],
            gateOpensAt: $data['gate_opens_at'] ?? null,
            capacity: isset($data['capacity']) ? (int) $data['capacity'] : null,
            status: $data['status'] ?? 'rascunho',
            salesStartAt: $data['sales_start_at'] ?? null,
            salesEndAt: $data['sales_end_at'] ?? null,
        );
    }
}
