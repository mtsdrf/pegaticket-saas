<?php

namespace App\Events\Affiliate;

class AffiliateUpdated
{
    public function __construct(
        public string $affiliateUuid,
        public ?int $actorId,
    ) {}
}
