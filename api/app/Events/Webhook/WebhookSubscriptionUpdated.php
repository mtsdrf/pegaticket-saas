<?php

namespace App\Events\Webhook;

class WebhookSubscriptionUpdated
{
    public function __construct(
        public string $subscriptionUuid,
        public int $tenantId,
        public int $actorId,
    ) {
    }
}
