<?php

namespace App\Exceptions\Subscription;

/**
 * Arrependimento solicitado fora da janela legal de 7 dias
 * (SubscriptionService::requestWithdrawal).
 */
class WithdrawalWindowExpiredException extends \RuntimeException
{
}
