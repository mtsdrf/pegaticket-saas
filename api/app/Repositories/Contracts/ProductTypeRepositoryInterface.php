<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface ProductTypeRepositoryInterface extends BaseRepositoryInterface
{
    public function nameExists(int $tenantId, string $name, ?int $excludeId = null): bool;
    public function getActiveTypes(int $tenantId): Collection;
}
