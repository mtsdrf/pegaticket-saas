<?php

namespace App\Events\Sale;

class SalePaymentRefundRequested
{
    public function __construct(
        public string $orderUuid,
        public string $refundProtocol,
        public ?int $actorId
    ) {
    }
}
