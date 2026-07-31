<?php

namespace App\Events\Order;

class OrderInstallmentUpdated
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
