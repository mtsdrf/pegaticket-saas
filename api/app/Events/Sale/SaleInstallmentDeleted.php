<?php

namespace App\Events\Sale;

class SaleInstallmentDeleted
{
    public function __construct(
        public string $orderUuid,
        public string $installmentUuid,
        public int $actorId
    ) {
    }
}
