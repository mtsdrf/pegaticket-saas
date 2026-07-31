<?php

namespace App\Listeners\Webhook;

use App\Events\Webhook\WebhookSubscriptionDeleted;
use App\Models\AuditLog;

class AuditWebhookSubscriptionDeleted
{
    public function handle(WebhookSubscriptionDeleted $event): void
    {
        AuditLog::record(
            event: 'webhook_subscription_deleted',
            model: null,
            meta: [
                'webhook_subscription_uuid' => $event->subscriptionUuid,
                'tenant_id' => $event->tenantId,
            ],
            actorId: $event->actorId
        );
    }
}
