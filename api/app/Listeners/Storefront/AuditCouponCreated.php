<?php

namespace App\Listeners\Storefront;

use App\Events\Storefront\CouponCreated;
use App\Models\AuditLog;

class AuditCouponCreated
{
    public function handle(CouponCreated $event): void
    {
        AuditLog::record(
            event: 'coupon_created',
            model: null,
            meta: ['coupon_uuid' => $event->couponUuid],
            actorId: $event->actorId
        );
    }
}
