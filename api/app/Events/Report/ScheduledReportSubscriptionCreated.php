<?php

namespace App\Events\Report;

class ScheduledReportSubscriptionCreated
{
    public function __construct(
        public string $subscriptionUuid,
        public int $tenantId,
        public string $recipientEmail,
        public int $actorId,
    ) {}
}
