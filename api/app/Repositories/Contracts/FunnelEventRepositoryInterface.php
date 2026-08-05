<?php

namespace App\Repositories\Contracts;

use App\Models\Storefront\StorefrontFunnelEvent;

/**
 * Contrato dedicado (não estende BaseRepositoryInterface): mesmo espírito
 * de CartEventRepositoryInterface — ledger de telemetria, write-only pela
 * API pública. Leitura agregada fica em FunnelAnalyticsService (staff).
 */
interface FunnelEventRepositoryInterface
{
    public function store(array $data): StorefrontFunnelEvent;
}
