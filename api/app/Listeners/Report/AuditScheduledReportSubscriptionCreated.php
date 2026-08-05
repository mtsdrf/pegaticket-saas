<?php

namespace App\Listeners\Report;

use App\Events\Report\ScheduledReportSubscriptionCreated;
use App\Models\AuditLog;

class AuditScheduledReportSubscriptionCreated
{
    public function handle(ScheduledReportSubscriptionCreated $event): void
    {
        AuditLog::record(
            event: 'scheduled_report_subscription_created',
            model: null,
            meta: [
                'scheduled_report_subscription_uuid' => $event->subscriptionUuid,
                'tenant_id' => $event->tenantId,
                'recipient_email' => $event->recipientEmail,
            ],
            actorId: $event->actorId
        );
    }
}
