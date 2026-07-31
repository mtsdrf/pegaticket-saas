<?php

namespace App\Events\Order;

class OrderInstallmentDeleted
{
    public function __construct(
        public string $orderUuid,
        public string $installmentUuid,
        public int $actorId
    ) {
    }
}
