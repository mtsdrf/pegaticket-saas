<?php

namespace App\Events\Report;

class ScheduledReportSubscriptionCancelled
{
    public function __construct(
        public string $subscriptionUuid,
        public int $tenantId,
        public int $actorId,
    ) {}
}
