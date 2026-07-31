<?php

namespace App\Listeners\Storefront;

use App\Events\Storefront\CouponUpdated;
use App\Models\AuditLog;

class AuditCouponUpdated
{
    public function handle(CouponUpdated $event): void
    {
        AuditLog::record(
            event: 'coupon_updated',
            model: null,
            meta: ['coupon_uuid' => $event->couponUuid],
            actorId: $event->actorId
        );
    }
}
