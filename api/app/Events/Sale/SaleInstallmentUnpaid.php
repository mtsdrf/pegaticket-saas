<?php

namespace App\Events\Sale;

class SaleInstallmentUnpaid
{
    public function __construct(
        public string $orderUuid,
        public string $installmentUuid,
        public int $actorId
    ) {
    }
}
