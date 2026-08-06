<?php

namespace App\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface CustomReportDefinitionRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator;
}
