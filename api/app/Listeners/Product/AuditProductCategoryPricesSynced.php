<?php

namespace App\Listeners\Product;

use App\Events\Product\ProductCategoryPricesSynced;
use App\Models\AuditLog;

class AuditProductCategoryPricesSynced
{
    public function handle(ProductCategoryPricesSynced $event): void
    {
        AuditLog::record(
            event: 'product_category_prices_synced',
            model: null,
            meta: [
                'product_uuid' => $event->productUuid,
                'category_prices_in_payload' => $event->categoryPrices,
            ],
            actorId: $event->actorId
        );
    }
}
