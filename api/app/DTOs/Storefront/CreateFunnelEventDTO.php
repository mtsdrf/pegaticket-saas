<?php

namespace App\DTOs\Storefront;

class CreateFunnelEventDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $eventId,
        public readonly string $sessionId,
        public readonly string $step,
    ) {}

    public static function fromArray(array $data, int $tenantId, int $eventId): self
    {
        return new self(
            tenantId: $tenantId,
            eventId: $eventId,
            sessionId: $data['session_id'],
            step: $data['step'],
        );
    }
}
