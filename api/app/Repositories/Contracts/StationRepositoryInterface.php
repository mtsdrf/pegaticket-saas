<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface StationRepositoryInterface extends BaseRepositoryInterface
{
    public function listForTenant(int $tenantId): Collection;

    public function findByUuidForTenant(string $uuid, int $tenantId): ?\App\Models\Balcao\Station;

    public function nameExists(int $tenantId, string $name, ?int $excludeId = null): bool;
}
