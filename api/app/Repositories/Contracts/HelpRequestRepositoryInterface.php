<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface HelpRequestRepositoryInterface extends BaseRepositoryInterface
{
    public function listForTenant(int $tenantId, int $limit = 50): Collection;
}
