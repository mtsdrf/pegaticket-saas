<?php

namespace App\Events\Sale;

class SaleInstallmentUnpaid
{
    public function __construct(
        public string $saleUuid,
        public string $installmentUuid,
        public int $actorId
    ) {
    }
}
