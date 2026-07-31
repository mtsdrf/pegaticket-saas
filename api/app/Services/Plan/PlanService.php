<?php

namespace App\Services\Plan;

use App\DTOs\Plan\CreatePlanDTO;
use App\DTOs\Plan\UpdatePlanDTO;
use App\Events\Plan\PlanCreated;
use App\Events\Plan\PlanDeleted;
use App\Events\Plan\PlanUpdated;
use App\Models\Plan\Plan;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Support\GridQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlanService
{
    public function __construct(
        private PlanRepositoryInterface $repository
    ) {
    }

    public function paginate(
        int $perPage = 15,
        array $filters = [],
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $sortable = [
            'name' => 'plans.name',
            'slug' => 'plans.slug',
            'description' => 'plans.description',
            'sort_order' => 'plans.sort_order',
            'is_active' => 'plans.is_active',
        ];

        $query = Plan::query()
            ->select([
                'plans.id',
                'plans.uuid',
                'plans.name',
                'plans.slug',
                'plans.description',
                'plans.sort_order',
                'plans.is_active',
                'plans.created_at',
                'plans.updated_at',
            ])
            ->whereNull('plans.deleted_at');

        GridQuery::applyTextFilters($query, $filters, [
            'name' => 'plans.name',
            'slug' => 'plans.slug',
            'description' => 'plans.description',
        ]);

        GridQuery::applyBooleanFilters($query, $filters, [
            'is_active' => 'plans.is_active',
        ]);

        $sortColumn = is_string($sortBy) ? ($sortable[$sortBy] ?? null) : null;
        $query->orderBy($sortColumn ?? 'plans.sort_order', GridQuery::normalizeSortDir($sortDir))
            ->orderBy('plans.name');

        return $query->paginate($perPage);
    }

    public function create(CreatePlanDTO $dto): Plan
    {
        return DB::transaction(function () use ($dto) {
            $plan = $this->repository->create([
                'name' => $dto->name,
                'slug' => $dto->slug,
                'description' => $dto->description,
                'sort_order' => $dto->sortOrder,
                'is_active' => $dto->isActive,
            ]);

            event(new PlanCreated(
                planUuid: $plan->uuid,
                actorId: Auth::id()
            ));

            return $plan;
        });
    }

    public function update(Plan $plan, UpdatePlanDTO $dto): Plan
    {
        return DB::transaction(function () use ($plan, $dto) {
            $original = $plan->getOriginal();

            $plan = $this->repository->update($plan, [
                'name' => $dto->name,
                'slug' => $dto->slug,
                'description' => $dto->description,
                'sort_order' => $dto->sortOrder,
                'is_active' => $dto->isActive,
            ]);

            $changes = array_keys(array_diff_assoc($plan->getAttributes(), $original));

            if ($changes !== []) {
                event(new PlanUpdated(
                    planUuid: $plan->uuid,
                    actorId: Auth::id(),
                    changes: $changes
                ));
            }

            return $plan;
        });
    }

    public function delete(Plan $plan): void
    {
        DB::transaction(function () use ($plan) {
            $this->repository->delete($plan);

            event(new PlanDeleted(
                planUuid: $plan->uuid,
                actorId: Auth::id()
            ));
        });
    }
}
