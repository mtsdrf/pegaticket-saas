<?php

namespace App\Services\Plan;

use App\DTOs\Plan\SyncPlanFunctionalitiesDTO;
use App\Events\Plan\PlanFunctionalitiesSynced;
use App\Models\Plan\Plan;
use App\Repositories\Contracts\PlanFunctionalityRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlanFunctionalityService
{
    public function __construct(
        private PlanFunctionalityRepositoryInterface $repository
    ) {
    }

    public function getFunctionalities(Plan $plan)
    {
        return $this->repository->getPlanFunctionalities($plan->id);
    }

    public function syncFunctionalities(Plan $plan, SyncPlanFunctionalitiesDTO $dto): void
    {
        DB::transaction(function () use ($plan, $dto) {
            $this->repository->syncFunctionalities(
                $plan->id,
                $dto->functionalities
            );

            event(new PlanFunctionalitiesSynced(
                planUuid: $plan->uuid,
                actorId: Auth::id()
            ));
        });
    }
}
