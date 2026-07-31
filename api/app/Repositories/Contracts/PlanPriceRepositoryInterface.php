<?php

namespace App\Repositories\Contracts;

use App\Models\Subscription\PlanPrice;

interface PlanPriceRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Preço vigente ativo para um plano + período de cobrança, respeitando
     * a janela valid_from/valid_to (nullable = sem limite). O mais recente
     * vence quando há mais de um vigente.
     */
    public function findActive(int $planId, string $billingPeriod): ?PlanPrice;
}
