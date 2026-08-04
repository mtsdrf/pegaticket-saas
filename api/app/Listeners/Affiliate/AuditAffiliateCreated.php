<?php

namespace App\Listeners\Affiliate;

use App\Events\Affiliate\AffiliateCreated;
use App\Models\AuditLog;

class AuditAffiliateCreated
{
    public function handle(AffiliateCreated $event): void
    {
        AuditLog::record(
            event: 'affiliate_created',
            model: null,
            meta: ['affiliate_uuid' => $event->affiliateUuid],
            actorId: $event->actorId
        );
    }
}
