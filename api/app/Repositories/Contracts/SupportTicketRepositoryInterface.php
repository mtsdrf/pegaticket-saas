<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface SupportTicketRepositoryInterface extends BaseRepositoryInterface
{
    public function listForTenant(int $tenantId, int $limit = 50): Collection;
}
