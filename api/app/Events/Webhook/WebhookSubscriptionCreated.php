<?php

namespace App\Events\Webhook;

class WebhookSubscriptionCreated
{
    public function __construct(
        public string $subscriptionUuid,
        public int $tenantId,
        public int $actorId,
    ) {
    }
}
