<?php

namespace App\Repositories\Contracts;

use App\Models\Tenant\TenantPagBankConnection;

interface TenantPagBankConnectionRepositoryInterface extends BaseRepositoryInterface
{
    public function findForTenant(int $tenantId): ?TenantPagBankConnection;

    /**
     * Busca a conexão pendente (status pending_connection) cujo
     * connect_state bate com o valor recebido no callback do PagBank.
     */
    public function findPendingByState(string $state): ?TenantPagBankConnection;
}
