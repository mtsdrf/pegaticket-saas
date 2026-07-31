<?php

namespace App\Repositories\Contracts;

use App\Models\Privacy\PrivacyRequest;
use Illuminate\Support\Collection;

interface PrivacyRequestRepositoryInterface extends BaseRepositoryInterface
{
    public function listForTenant(int $tenantId, int $limit = 50): Collection;

    public function findForTenantByUuid(int $tenantId, string $uuid): ?PrivacyRequest;
}
