<?php

namespace App\Repositories\Eloquent;

use App\Models\Pdv\CashSession;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use Illuminate\Support\Collection;

class CashSessionRepository extends BaseRepository implements CashSessionRepositoryInterface
{
    public function __construct(CashSession $model)
    {
        parent::__construct($model);
    }

    public function listForTenant(int $tenantId, int $limit = 50): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->with(['cashRegister', 'movements'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function openSessionForTenant(int $tenantId): ?CashSession
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('status', CashSession::STATUS_OPEN)
            ->with(['cashRegister', 'movements'])
            ->orderByDesc('id')
            ->first();
    }

    public function openSessionForRegister(int $cashRegisterId): ?CashSession
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('cash_register_id', $cashRegisterId)
            ->where('status', CashSession::STATUS_OPEN)
            ->orderByDesc('id')
            ->first();
    }

    public function findByUuidForTenant(string $uuid, int $tenantId): ?CashSession
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('uuid', $uuid)
            ->where('tenant_id', $tenantId)
            ->with(['cashRegister', 'movements'])
            ->first();
    }
}
