<?php

namespace App\Listeners\Webhook;

use App\Events\Webhook\WebhookSubscriptionUpdated;
use App\Models\AuditLog;

class AuditWebhookSubscriptionUpdated
{
    public function handle(WebhookSubscriptionUpdated $event): void
    {
        AuditLog::record(
            event: 'webhook_subscription_updated',
            model: null,
            meta: [
                'webhook_subscription_uuid' => $event->subscriptionUuid,
                'tenant_id' => $event->tenantId,
            ],
            actorId: $event->actorId
        );
    }
}
