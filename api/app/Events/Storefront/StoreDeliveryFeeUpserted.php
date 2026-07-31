<?php

namespace App\Events\Storefront;

class StoreDeliveryFeeUpserted
{
    public function __construct(
        public string $storeDeliveryFeeUuid,
        public int $actorId
    ) {
    }
}
