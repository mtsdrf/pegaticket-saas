<?php

namespace App\Events\Sale;

class SalePaymentRefundRequested
{
    public function __construct(
        public string $saleUuid,
        public string $refundProtocol,
        public ?int $actorId
    ) {
    }
}
