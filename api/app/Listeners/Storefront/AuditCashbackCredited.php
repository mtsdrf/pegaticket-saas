<?php

namespace App\Listeners\Storefront;

use App\Events\Storefront\CashbackCredited;
use App\Models\AuditLog;

class AuditCashbackCredited
{
    public function handle(CashbackCredited $event): void
    {
        AuditLog::record(
            event: 'cashback_credited',
            model: null,
            meta: [
                'cashback_earning_uuid' => $event->cashbackEarningUuid,
                'final_customer_id' => $event->finalCustomerId,
                'amount_cents' => $event->amountCents,
            ],
            actorId: $event->actorId
        );
    }
}
