<?php

namespace App\Events\Sale;

class SaleRefundCreated
{
    /**
     * @param list<string> $ticketUuids
     */
    public function __construct(
        public string $saleUuid,
        public string $saleRefundUuid,
        public string $type,
        public float $amount,
        public bool $releaseSeats,
        public array $ticketUuids,
        public int $actorId
    ) {
    }
}
