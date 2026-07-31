<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface PlanFunctionalityRepositoryInterface extends BaseRepositoryInterface
{
    public function syncFunctionalities(int $planId, array $functionalities): void;
    public function getPlanFunctionalities(int $planId): Collection;
}
