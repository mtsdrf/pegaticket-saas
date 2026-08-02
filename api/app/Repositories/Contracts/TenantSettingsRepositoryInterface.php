<?php

namespace App\Repositories\Contracts;

use App\Models\Tenant\TenantSettings;

interface TenantSettingsRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Retorna a linha de configurações do tenant, criando com os
     * defaults na primeira leitura se ainda não existir. Tabela singleton
     * (1 linha por tenant).
     */
    public function findOrCreateForTenant(int $tenantId): TenantSettings;
}
