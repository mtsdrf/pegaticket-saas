<?php

namespace App\Repositories\Contracts;

use App\Models\Storefront\ReactivationRule;

interface ReactivationRuleRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Retorna a regra de reativação do tenant, criando com os defaults
     * (is_active = false) na primeira leitura se ainda não existir.
     * Tabela singleton (1 linha por tenant), mesmo padrão de
     * TenantSettingsRepositoryInterface::findOrCreateForTenant().
     */
    public function findOrCreateForTenant(int $tenantId): ReactivationRule;

    /**
     * Regras ativas de todos os tenants — usado pelo comando agendado
     * reactivation:process, que itera 1 tenant por vez.
     */
    public function listActive(): \Illuminate\Support\Collection;
}
