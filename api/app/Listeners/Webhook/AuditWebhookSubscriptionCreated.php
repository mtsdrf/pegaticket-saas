<?php

namespace App\Listeners\Webhook;

use App\Events\Webhook\WebhookSubscriptionCreated;
use App\Models\AuditLog;

class AuditWebhookSubscriptionCreated
{
    public function handle(WebhookSubscriptionCreated $event): void
    {
        AuditLog::record(
            event: 'webhook_subscription_created',
            model: null,
            meta: [
                'webhook_subscription_uuid' => $event->subscriptionUuid,
                'tenant_id' => $event->tenantId,
            ],
            actorId: $event->actorId
        );
    }
}
