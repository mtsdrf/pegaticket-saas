<?php

namespace App\Listeners\Storefront;

use App\Events\Storefront\StoreBusinessHoursUpdated;
use App\Models\AuditLog;

class AuditStoreBusinessHoursUpdated
{
    public function handle(StoreBusinessHoursUpdated $event): void
    {
        AuditLog::record(
            event: 'store_business_hours_updated',
            model: null,
            meta: ['tenant_id' => $event->tenantId],
            actorId: $event->actorId
        );
    }
}
