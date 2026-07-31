<?php

namespace App\Repositories\Eloquent;

use App\Models\Fiscal\FiscalOperationProfile;
use App\Repositories\Contracts\FiscalOperationProfileRepositoryInterface;
use Illuminate\Support\Collection;

class FiscalOperationProfileRepository extends BaseRepository implements FiscalOperationProfileRepositoryInterface
{
    public function __construct(FiscalOperationProfile $model)
    {
        parent::__construct($model);
    }

    public function listForTenant(int $tenantId): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }
}
