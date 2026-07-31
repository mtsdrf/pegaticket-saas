<?php

namespace App\Events\Order;

class OrderPaymentRefundRequested
{
    public function __construct(
        public string $orderUuid,
        public string $refundProtocol,
        public ?int $actorId
    ) {
    }
}
