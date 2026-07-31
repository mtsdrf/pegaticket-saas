<?php

namespace App\DTOs\Event;

class CreateTicketBatchDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $ticketTypeUuid,
        public readonly string $name,
        public readonly float $price,
        public readonly int $quantity,
        public readonly ?string $startsAt,
        public readonly ?string $endsAt,
        public readonly int $priority,
        public readonly bool $autoAdvance,
        public readonly string $status,
    ) {
    }

    public static function fromArray(array $data, int $tenantId, string $ticketTypeUuid): self
    {
        return new self(
            tenantId: $tenantId,
            ticketTypeUuid: $ticketTypeUuid,
            name: $data['name'],
            price: (float) $data['price'],
            quantity: (int) $data['quantity'],
            startsAt: $data['starts_at'] ?? null,
            endsAt: $data['ends_at'] ?? null,
            priority: isset($data['priority']) ? (int) $data['priority'] : 0,
            autoAdvance: (bool) ($data['auto_advance'] ?? true),
            status: $data['status'] ?? 'rascunho',
        );
    }
}
