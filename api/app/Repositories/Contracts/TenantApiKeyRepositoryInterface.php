<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface TenantApiKeyRepositoryInterface extends BaseRepositoryInterface
{
    public function listForTenant(int $tenantId): Collection;

    public function findActiveByHash(string $keyHash): ?\App\Models\ApiKey\TenantApiKey;
}
