<?php

namespace App\Events\Sale;

class SaleInstallmentCreated
{
    public function __construct(
        public string $orderUuid,
        public string $installmentUuid,
        public int $actorId
    ) {
    }
}
