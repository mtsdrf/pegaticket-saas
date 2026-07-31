<?php

namespace App\Events\Storefront;

class StoreDeliveryFeeDeleted
{
    public function __construct(
        public string $storeDeliveryFeeUuid,
        public int $actorId
    ) {
    }
}
