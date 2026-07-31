<?php

namespace App\Listeners\Storefront;

use App\Events\Storefront\ProductPromotionUpserted;
use App\Models\AuditLog;

class AuditProductPromotionUpserted
{
    public function handle(ProductPromotionUpserted $event): void
    {
        AuditLog::record(
            event: 'product_promotion_upserted',
            model: null,
            meta: ['product_promotion_uuid' => $event->productPromotionUuid],
            actorId: $event->actorId
        );
    }
}
