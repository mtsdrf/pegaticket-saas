<?php

namespace App\Listeners\Storefront;

use App\Events\Storefront\CouponDeleted;
use App\Models\AuditLog;

class AuditCouponDeleted
{
    public function handle(CouponDeleted $event): void
    {
        AuditLog::record(
            event: 'coupon_deleted',
            model: null,
            meta: ['coupon_uuid' => $event->couponUuid],
            actorId: $event->actorId
        );
    }
}
