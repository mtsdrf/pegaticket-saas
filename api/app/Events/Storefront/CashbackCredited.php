<?php

namespace App\Events\Storefront;

class CashbackCredited
{
    public function __construct(
        public readonly string $cashbackEarningUuid,
        public readonly int $tenantId,
        public readonly int $finalCustomerId,
        public readonly int $amountCents,
        public readonly ?int $actorId,
    ) {
    }
}
