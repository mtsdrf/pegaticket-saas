<?php

namespace App\Listeners\Storefront;

use App\Events\Storefront\StoreDeliveryFeeUpserted;
use App\Models\AuditLog;

class AuditStoreDeliveryFeeUpserted
{
    public function handle(StoreDeliveryFeeUpserted $event): void
    {
        AuditLog::record(
            event: 'store_delivery_fee_upserted',
            model: null,
            meta: ['store_delivery_fee_uuid' => $event->storeDeliveryFeeUuid],
            actorId: $event->actorId
        );
    }
}
