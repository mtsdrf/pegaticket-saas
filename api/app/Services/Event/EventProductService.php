<?php

namespace App\Services\Event;

use App\DTOs\Event\CreateEventProductDTO;
use App\DTOs\Event\UpdateEventProductDTO;
use App\Events\Event\EventProductCreated;
use App\Events\Event\EventProductUpdated;
use App\Events\Event\EventProductDeleted;
use App\Models\Event\Event;
use App\Models\Event\EventProduct;
use App\Repositories\Contracts\EventProductRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EventProductService
{
    public const EAGER_RELATIONS = ['event'];

    public function __construct(
        private EventProductRepositoryInterface $repository
    ) {
    }

    public function find(EventProduct $eventProduct): EventProduct
    {
        $this->assertBelongsToCurrentTenant($eventProduct);

        return $eventProduct;
    }

    private const SORTABLE = [
        'name' => 'event_products.name',
        'price' => 'event_products.price',
        'status' => 'event_products.status',
    ];

    public function paginate(
        int $tenantId,
        array $filters = [],
        int $perPage = 15,
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $query = EventProduct::query()
            ->where('event_products.tenant_id', $tenantId)
            ->whereNull('event_products.deleted_at')
            ->with(self::EAGER_RELATIONS);

        if (!empty($filters['name'])) {
            $query->where('event_products.name', 'like', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['event_uuid'])) {
            $query->whereHas('event', fn($q) => $q->where('uuid', $filters['event_uuid']));
        }

        if (!empty($filters['kind'])) {
            $query->where('event_products.kind', $filters['kind']);
        }

        if (!empty($filters['status'])) {
            $query->where('event_products.status', $filters['status']);
        }

        if (!empty($filters['q'])) {
            $term = $filters['q'];
            $query->where(function ($sub) use ($term) {
                $sub->where('event_products.name', 'like', '%' . $term . '%');
            });
        }

        $sortColumn = self::SORTABLE[$sortBy] ?? null;
        $dir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        if ($sortColumn) {
            $query->orderBy($sortColumn, $dir);
        } else {
            $query->orderBy('event_products.name');
        }

        return $query->paginate($perPage);
    }

    public function create(CreateEventProductDTO $dto): EventProduct
    {
        return DB::transaction(function () use ($dto) {
            $event = Event::where('uuid', $dto->eventUuid)
                ->where('tenant_id', $dto->tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $eventProduct = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'event_id' => $event->id,
                'name' => $dto->name,
                'description' => $dto->description,
                'price' => $dto->price,
                'quantity_available' => $dto->quantityAvailable,
                'max_per_order' => $dto->maxPerOrder,
                'sales_start_at' => $dto->salesStartAt,
                'sales_end_at' => $dto->salesEndAt,
                'kind' => $dto->kind,
                'requires_plate' => $dto->requiresPlate,
                'requires_model' => $dto->requiresModel,
                'requires_color' => $dto->requiresColor,
                'status' => $dto->status,
            ]);

            event(new EventProductCreated(
                eventProductUuid: $eventProduct->uuid,
                actorId: Auth::id()
            ));

            return $eventProduct;
        });
    }

    public function update(EventProduct $eventProduct, UpdateEventProductDTO $dto): EventProduct
    {
        $this->assertBelongsToCurrentTenant($eventProduct);

        return DB::transaction(function () use ($eventProduct, $dto) {
            $original = $eventProduct->getOriginal();

            $data = array_filter([
                'name' => $dto->name,
                'description' => $dto->description,
                'price' => $dto->price,
                'quantity_available' => $dto->quantityAvailable,
                'max_per_order' => $dto->maxPerOrder,
                'sales_start_at' => $dto->salesStartAt,
                'sales_end_at' => $dto->salesEndAt,
                'kind' => $dto->kind,
                'requires_plate' => $dto->requiresPlate,
                'requires_model' => $dto->requiresModel,
                'requires_color' => $dto->requiresColor,
                'status' => $dto->status,
            ], fn($v) => !is_null($v));

            if ($dto->eventUuid) {
                $event = Event::where('uuid', $dto->eventUuid)
                    ->where('tenant_id', $eventProduct->tenant_id)
                    ->whereNull('deleted_at')
                    ->firstOrFail();

                $data['event_id'] = $event->id;
            }

            if (!empty($data)) {
                $eventProduct = $this->repository->update($eventProduct, $data);

                $changes = array_diff_assoc($eventProduct->getAttributes(), $original);

                if (!empty($changes)) {
                    event(new EventProductUpdated(
                        eventProductUuid: $eventProduct->uuid,
                        actorId: Auth::id(),
                        changes: array_keys($changes)
                    ));
                }
            }

            return $eventProduct;
        });
    }

    public function delete(EventProduct $eventProduct): void
    {
        $this->assertBelongsToCurrentTenant($eventProduct);

        DB::transaction(function () use ($eventProduct) {
            $this->repository->delete($eventProduct);

            event(new EventProductDeleted(
                eventProductUuid: $eventProduct->uuid,
                actorId: Auth::id()
            ));
        });
    }

    private function assertBelongsToCurrentTenant(EventProduct $eventProduct): void
    {
        if ((int) $eventProduct->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
