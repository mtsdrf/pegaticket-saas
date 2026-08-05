<?php

namespace App\Listeners\Report;

use App\Events\Report\ScheduledReportSubscriptionCancelled;
use App\Models\AuditLog;

class AuditScheduledReportSubscriptionCancelled
{
    public function handle(ScheduledReportSubscriptionCancelled $event): void
    {
        AuditLog::record(
            event: 'scheduled_report_subscription_cancelled',
            model: null,
            meta: [
                'scheduled_report_subscription_uuid' => $event->subscriptionUuid,
                'tenant_id' => $event->tenantId,
            ],
            actorId: $event->actorId
        );
    }
}
