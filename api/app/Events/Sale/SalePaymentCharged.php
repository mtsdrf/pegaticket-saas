<?php

namespace App\Events\Sale;

class SalePaymentCharged
{
    public function __construct(
        public string $orderUuid,
        public string $paymentUuid,
        public string $method,
        public ?int $actorId
    ) {
    }
}
