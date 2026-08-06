<?php

namespace App\Repositories\Eloquent;

use App\Models\Report\CustomReportDefinition;
use App\Repositories\Contracts\CustomReportDefinitionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomReportDefinitionRepository extends BaseRepository implements CustomReportDefinitionRepositoryInterface
{
    public function __construct(CustomReportDefinition $model)
    {
        parent::__construct($model);
    }

    public function paginateForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
