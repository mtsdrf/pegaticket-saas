<?php

namespace App\Events\Storefront;

class ReactivationDispatched
{
    public function __construct(
        public readonly string $reactivationDispatchUuid,
        public readonly int $tenantId,
        public readonly int $clientId,
        public readonly ?int $finalCustomerId,
        public readonly string $couponCode,
    ) {
    }
}
