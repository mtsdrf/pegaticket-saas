<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SalePaymentCharged;
use App\Models\AuditLog;

class AuditSalePaymentCharged
{
    public function handle(SalePaymentCharged $event): void
    {
        AuditLog::record(
            event: 'order_payment_charged',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
                'payment_uuid' => $event->paymentUuid,
                'method' => $event->method,
            ],
            actorId: $event->actorId
        );
    }
}
