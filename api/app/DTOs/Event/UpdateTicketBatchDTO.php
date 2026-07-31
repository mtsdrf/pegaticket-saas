<?php

namespace App\DTOs\Event;

class UpdateTicketBatchDTO
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?float $price,
        public readonly ?int $quantity,
        public readonly ?string $startsAt,
        public readonly ?string $endsAt,
        public readonly ?int $priority,
        public readonly ?bool $autoAdvance,
        public readonly ?string $status,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            price: isset($data['price']) ? (float) $data['price'] : null,
            quantity: isset($data['quantity']) ? (int) $data['quantity'] : null,
            startsAt: $data['starts_at'] ?? null,
            endsAt: $data['ends_at'] ?? null,
            priority: isset($data['priority']) ? (int) $data['priority'] : null,
            autoAdvance: array_key_exists('auto_advance', $data) ? (bool) $data['auto_advance'] : null,
            status: $data['status'] ?? null,
        );
    }
}
