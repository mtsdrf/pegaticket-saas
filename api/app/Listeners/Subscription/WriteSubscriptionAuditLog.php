<?php

namespace App\Listeners\Subscription;

use App\Events\Subscription\SubscriptionCanceled;
use App\Events\Subscription\SubscriptionCreated;
use App\Events\Subscription\SubscriptionPlanChanged;
use App\Events\Subscription\SubscriptionWithdrawalRequested;
use App\Models\AuditLog;

/**
 * Listener catch-all de auditoria de assinatura (roadmap 1B) — mesmo padrão
 * de WritePortalAuditLog: bounded context pequeno o bastante para não
 * justificar uma classe Audit* por evento.
 */
class WriteSubscriptionAuditLog
{
    public function handle(object $event): void
    {
        [$auditEvent, $meta, $actorId] = match (true) {
            $event instanceof SubscriptionCreated => [
                'subscription_created',
                ['subscription_uuid' => $event->subscriptionUuid],
                $event->actorId,
            ],
            $event instanceof SubscriptionPlanChanged => [
                'subscription_plan_changed',
                ['subscription_uuid' => $event->subscriptionUuid],
                $event->actorId,
            ],
            $event instanceof SubscriptionCanceled => [
                'subscription_canceled',
                ['subscription_uuid' => $event->subscriptionUuid, 'immediately' => $event->immediately],
                $event->actorId,
            ],
            $event instanceof SubscriptionWithdrawalRequested => [
                'subscription_withdrawal_requested',
                ['subscription_uuid' => $event->subscriptionUuid, 'refund_protocol' => $event->refundProtocol],
                $event->actorId,
            ],
            default => [null, [], null],
        };

        if ($auditEvent === null) {
            return;
        }

        AuditLog::record(
            event: $auditEvent,
            model: null,
            meta: $meta,
            actorId: $actorId
        );
    }
}
