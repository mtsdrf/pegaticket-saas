<?php

namespace App\Repositories\Eloquent;

use App\Models\Subscription\Refund;
use App\Repositories\Contracts\RefundRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RefundRepository extends BaseRepository implements RefundRepositoryInterface
{
    public function __construct(Refund $model)
    {
        parent::__construct($model);
    }

    public function paginateForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
