<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface StoreBusinessHourRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Linhas já persistidas do tenant, ordenadas por day_of_week — pode
     * retornar menos de 7 (dias ainda não configurados).
     */
    public function getForTenant(int $tenantId): Collection;

    /**
     * Upsert por (tenant_id, day_of_week) — nunca duplica linha, nunca
     * deixa menos de 7 depois de salvar. $days é um array de exatamente 7
     * itens já validados (day_of_week 0-6 distintos).
     */
    public function upsertForTenant(int $tenantId, array $days): Collection;
}
