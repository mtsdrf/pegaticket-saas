<?php

namespace App\Services\Event;

use App\DTOs\Event\CreateEventCategoryDTO;
use App\DTOs\Event\UpdateEventCategoryDTO;
use App\Events\Event\EventCategoryCreated;
use App\Events\Event\EventCategoryUpdated;
use App\Events\Event\EventCategoryDeleted;
use App\Models\Event\EventCategory;
use App\Repositories\Contracts\EventCategoryRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EventCategoryService
{
    public function __construct(
        private EventCategoryRepositoryInterface $repository
    ) {
    }

    private const SORTABLE = [
        'name' => 'name',
        'priority' => 'priority',
        'is_active' => 'is_active',
    ];

    public function paginate(
        int $tenantId,
        array $filters = [],
        int $perPage = 15,
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $query = EventCategory::where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (array_key_exists('priority_min', $filters) && $filters['priority_min'] !== null && $filters['priority_min'] !== '') {
            $query->where('priority', '>=', $filters['priority_min']);
        }

        if (array_key_exists('priority_max', $filters) && $filters['priority_max'] !== null && $filters['priority_max'] !== '') {
            $query->where('priority', '<=', $filters['priority_max']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['q'])) {
            $term = $filters['q'];
            $query->where(function ($sub) use ($term) {
                $sub->where('name', 'like', '%' . $term . '%');
            });
        }

        $sortColumn = self::SORTABLE[$sortBy] ?? null;
        $dir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        if ($sortColumn) {
            $query->orderBy($sortColumn, $dir);
        } else {
            $query->orderBy('priority')->orderBy('name');
        }

        return $query->paginate($perPage);
    }

    public function create(CreateEventCategoryDTO $dto): EventCategory
    {
        return DB::transaction(function () use ($dto) {
            if ($this->repository->nameExists($dto->tenantId, $dto->name)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.event_category.name_exists'));
            }

            $category = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'name' => $dto->name,
                'priority' => $dto->priority,
                'is_active' => $dto->isActive,
            ]);

            event(new EventCategoryCreated(
                eventCategoryUuid: $category->uuid,
                actorId: Auth::id()
            ));

            return $category;
        });
    }

    public function update(EventCategory $category, UpdateEventCategoryDTO $dto): EventCategory
    {
        $this->assertBelongsToCurrentTenant($category);

        return DB::transaction(function () use ($category, $dto) {
            $original = $category->getOriginal();

            if ($dto->name && $this->repository->nameExists($category->tenant_id, $dto->name, $category->id)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.event_category.name_exists'));
            }

            $data = array_filter([
                'name' => $dto->name,
                'priority' => $dto->priority,
                'is_active' => $dto->isActive,
            ], fn($v) => !is_null($v));

            if (!empty($data)) {
                $category = $this->repository->update($category, $data);

                $changes = array_diff_assoc($category->getAttributes(), $original);

                event(new EventCategoryUpdated(
                    eventCategoryUuid: $category->uuid,
                    actorId: Auth::id(),
                    changes: array_keys($changes)
                ));
            }

            return $category;
        });
    }

    public function delete(EventCategory $category): void
    {
        $this->assertBelongsToCurrentTenant($category);

        DB::transaction(function () use ($category) {
            $this->repository->delete($category);

            event(new EventCategoryDeleted(
                eventCategoryUuid: $category->uuid,
                actorId: Auth::id()
            ));
        });
    }

    private function assertBelongsToCurrentTenant(EventCategory $category): void
    {
        if ((int) $category->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
