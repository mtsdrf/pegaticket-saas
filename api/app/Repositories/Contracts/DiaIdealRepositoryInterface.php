<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface DiaIdealRepositoryInterface extends BaseRepositoryInterface
{
    public function nameExists(int $tenantId, string $name, ?int $excludeId = null): bool;
    public function getActiveDiasIdeais(int $tenantId): Collection;
}
