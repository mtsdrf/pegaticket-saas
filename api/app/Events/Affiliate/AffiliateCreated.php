<?php

namespace App\Events\Affiliate;

class AffiliateCreated
{
    public function __construct(
        public string $affiliateUuid,
        public ?int $actorId,
    ) {}
}
