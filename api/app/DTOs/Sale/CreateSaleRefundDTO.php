<?php

namespace App\DTOs\Sale;

class CreateSaleRefundDTO
{
    /**
     * @param list<string>|null $ticketUuids Obrigatório e não vazio quando $type === 'parcial'.
     */
    public function __construct(
        public readonly string $type,
        public readonly float $amount,
        public readonly string $reason,
        public readonly string $refundedAt,
        public readonly ?string $externalReference,
        public readonly ?string $notes,
        public readonly bool $releaseSeats,
        public readonly ?array $ticketUuids,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            amount: (float) $data['amount'],
            reason: $data['reason'],
            refundedAt: $data['refunded_at'],
            externalReference: $data['external_reference'] ?? null,
            notes: $data['notes'] ?? null,
            releaseSeats: (bool) ($data['release_seats'] ?? false),
            ticketUuids: $data['ticket_uuids'] ?? null,
        );
    }
}
