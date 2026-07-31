<?php

namespace App\Listeners\Storefront;

use App\Events\Storefront\StoreDeliveryFeeDeleted;
use App\Models\AuditLog;

class AuditStoreDeliveryFeeDeleted
{
    public function handle(StoreDeliveryFeeDeleted $event): void
    {
        AuditLog::record(
            event: 'store_delivery_fee_deleted',
            model: null,
            meta: ['store_delivery_fee_uuid' => $event->storeDeliveryFeeUuid],
            actorId: $event->actorId
        );
    }
}
