<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface PeriodoIdealRepositoryInterface extends BaseRepositoryInterface
{
    public function nameExists(int $tenantId, string $name, ?int $excludeId = null): bool;
    public function getActivePeriodosIdeais(int $tenantId): Collection;
}
