<?php

namespace App\Events\Sale;

class SaleInstallmentUpdated
{
    /**
     * @param array<int, string> $changes
     */
    public function __construct(
        public string $orderUuid,
        public string $installmentUuid,
        public int $actorId,
        public array $changes = []
    ) {
    }
}
