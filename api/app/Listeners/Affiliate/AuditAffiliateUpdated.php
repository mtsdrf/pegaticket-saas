<?php

namespace App\Listeners\Affiliate;

use App\Events\Affiliate\AffiliateUpdated;
use App\Models\AuditLog;

class AuditAffiliateUpdated
{
    public function handle(AffiliateUpdated $event): void
    {
        AuditLog::record(
            event: 'affiliate_updated',
            model: null,
            meta: ['affiliate_uuid' => $event->affiliateUuid],
            actorId: $event->actorId
        );
    }
}
