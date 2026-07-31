<?php

namespace App\Exceptions\Subscription;

/**
 * Não há plan_price ativo/vigente para o plano + período informados
 * (SubscriptionService::create/changePlan).
 */
class NoActivePlanPriceException extends \RuntimeException
{
}
