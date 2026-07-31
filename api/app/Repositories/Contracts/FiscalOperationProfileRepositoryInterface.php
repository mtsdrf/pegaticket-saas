<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface FiscalOperationProfileRepositoryInterface extends BaseRepositoryInterface
{
    public function listForTenant(int $tenantId): Collection;
}
