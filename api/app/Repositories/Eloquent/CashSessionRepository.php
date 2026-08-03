<?php

namespace App\Repositories\Eloquent;

use App\Models\CashSession\CashSession;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class CashSessionRepository extends BaseRepository implements CashSessionRepositoryInterface
{
    public function __construct(CashSession $model)
    {
        parent::__construct($model);
    }

    public function findOpenForTenant(int $tenantId, bool $lock = false): ?CashSession
    {
        $query = CashSession::where('tenant_id', $tenantId)
            ->where('status', CashSession::STATUS_OPEN);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function paginateForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return CashSession::where('tenant_id', $tenantId)
            ->orderByDesc('opened_at')
            ->paginate($perPage);
    }
}
