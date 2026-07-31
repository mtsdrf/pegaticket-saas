<?php

namespace App\DTOs\Storefront;

class CreateCartEventDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $sessionId,
        public readonly string $eventType,
        public readonly array $payload,
    ) {
    }

    public static function fromArray(array $data, int $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            sessionId: $data['session_id'],
            eventType: $data['event_type'],
            payload: [
                'items' => $data['items'] ?? [],
                'total_amount' => isset($data['total_amount']) ? (float) $data['total_amount'] : null,
                'occurred_at' => $data['occurred_at'] ?? now()->toIso8601String(),
            ],
        );
    }
}
