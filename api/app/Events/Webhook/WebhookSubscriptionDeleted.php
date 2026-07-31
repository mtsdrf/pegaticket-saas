<?php

namespace App\Events\Webhook;

class WebhookSubscriptionDeleted
{
    public function __construct(
        public string $subscriptionUuid,
        public int $tenantId,
        public int $actorId,
    ) {
    }
}
