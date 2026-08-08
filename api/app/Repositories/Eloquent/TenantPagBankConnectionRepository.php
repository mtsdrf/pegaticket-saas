<?php

namespace App\Repositories\Eloquent;

use App\Models\Tenant\TenantPagBankConnection;
use App\Repositories\Contracts\TenantPagBankConnectionRepositoryInterface;

class TenantPagBankConnectionRepository extends BaseRepository implements TenantPagBankConnectionRepositoryInterface
{
    public function __construct(TenantPagBankConnection $model)
    {
        parent::__construct($model);
    }

    public function findForTenant(int $tenantId): ?TenantPagBankConnection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->first();
    }

    public function findPendingByState(string $state): ?TenantPagBankConnection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('connect_state', $state)
            ->where('status', TenantPagBankConnection::STATUS_PENDING_CONNECTION)
            ->first();
    }
}
