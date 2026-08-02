<?php

namespace App\Events\Sale;

class SaleInstallmentCreated
{
    public function __construct(
        public string $saleUuid,
        public string $installmentUuid,
        public int $actorId
    ) {
    }
}
