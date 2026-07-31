<?php

namespace App\Enums\Subscription;

/**
 * Estados possíveis de uma assinatura (roadmap 1B). A coluna
 * subscriptions.status é string no banco — este enum é a fonte de verdade
 * dos valores válidos e alimenta a SubscriptionStateMachine.
 */
enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Canceled = 'canceled';
    case CancelScheduled = 'cancel_scheduled';
}
