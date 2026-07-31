<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface TableRepositoryInterface extends BaseRepositoryInterface
{
    public function listForTenant(int $tenantId): Collection;

    public function findByUuidForTenant(string $uuid, int $tenantId): ?\App\Models\Balcao\Table;

    public function labelExists(int $tenantId, string $label, ?int $excludeId = null): bool;
}
