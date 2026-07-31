<?php

namespace App\DTOs\Event;

class UpdateEventSessionDTO
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $startsAt,
        public readonly ?string $endsAt,
        public readonly ?string $gateOpensAt,
        public readonly ?int $capacity,
        public readonly ?string $status,
        public readonly ?string $salesStartAt,
        public readonly ?string $salesEndAt,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            startsAt: $data['starts_at'] ?? null,
            endsAt: $data['ends_at'] ?? null,
            gateOpensAt: $data['gate_opens_at'] ?? null,
            capacity: array_key_exists('capacity', $data) ? ($data['capacity'] !== null ? (int) $data['capacity'] : null) : null,
            status: $data['status'] ?? null,
            salesStartAt: $data['sales_start_at'] ?? null,
            salesEndAt: $data['sales_end_at'] ?? null,
        );
    }
}
