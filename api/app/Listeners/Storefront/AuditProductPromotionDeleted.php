<?php

namespace App\Listeners\Storefront;

use App\Events\Storefront\ProductPromotionDeleted;
use App\Models\AuditLog;

class AuditProductPromotionDeleted
{
    public function handle(ProductPromotionDeleted $event): void
    {
        AuditLog::record(
            event: 'product_promotion_deleted',
            model: null,
            meta: ['product_promotion_uuid' => $event->productPromotionUuid],
            actorId: $event->actorId
        );
    }
}
