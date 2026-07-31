<?php

namespace App\Events\Order;

class OrderPaymentCharged
{
    public function __construct(
        public string $orderUuid,
        public string $paymentUuid,
        public string $method,
        public ?int $actorId
    ) {
    }
}
