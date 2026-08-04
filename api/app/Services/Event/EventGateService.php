<?php

namespace App\Services\Event;

use App\DTOs\Event\CreateEventGateDTO;
use App\DTOs\Event\UpdateEventGateDTO;
use App\Events\Event\EventGateCreated;
use App\Events\Event\EventGateDeleted;
use App\Events\Event\EventGateUpdated;
use App\Models\Event\Event;
use App\Models\Event\EventGate;
use App\Models\Event\TicketType;
use App\Repositories\Contracts\EventGateRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EventGateService
{
    public const EAGER_RELATIONS = ['event', 'allowedTicketTypes'];

    public function __construct(
        private EventGateRepositoryInterface $repository
    ) {}

    public function find(EventGate $eventGate): EventGate
    {
        $this->assertBelongsToCurrentTenant($eventGate);

        return $eventGate;
    }

    public function paginate(
        int $tenantId,
        string $eventUuid,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        $event = Event::where('uuid', $eventUuid)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $query = EventGate::query()
            ->where('event_gates.tenant_id', $tenantId)
            ->where('event_gates.event_id', $event->id)
            ->whereNull('event_gates.deleted_at')
            ->with(self::EAGER_RELATIONS);

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('event_gates.is_active', (bool) $filters['is_active']);
        }

        $query->orderBy('event_gates.name');

        return $query->paginate($perPage);
    }

    public function create(CreateEventGateDTO $dto): EventGate
    {
        return DB::transaction(function () use ($dto) {
            $event = Event::where('uuid', $dto->eventUuid)
                ->where('tenant_id', $dto->tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $eventGate = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'event_id' => $event->id,
                'name' => $dto->name,
                'is_active' => $dto->isActive,
            ]);

            if ($dto->ticketTypeUuids !== null) {
                $eventGate->allowedTicketTypes()->sync(
                    $this->resolveTicketTypeIds($dto->tenantId, $event->id, $dto->ticketTypeUuids)
                );
            }

            event(new EventGateCreated(
                eventGateUuid: $eventGate->uuid,
                actorId: Auth::id()
            ));

            return $eventGate;
        });
    }

    public function update(EventGate $eventGate, UpdateEventGateDTO $dto): EventGate
    {
        $this->assertBelongsToCurrentTenant($eventGate);

        return DB::transaction(function () use ($eventGate, $dto) {
            $original = $eventGate->getOriginal();
            $changes = [];

            $data = array_filter([
                'name' => $dto->name,
                'is_active' => $dto->isActive,
            ], fn ($v) => ! is_null($v));

            if (! empty($data)) {
                $eventGate = $this->repository->update($eventGate, $data);
                $changes = array_keys(array_diff_assoc($eventGate->getAttributes(), $original));
            }

            if ($dto->ticketTypeUuids !== null) {
                $eventGate->allowedTicketTypes()->sync(
                    $this->resolveTicketTypeIds($eventGate->tenant_id, $eventGate->event_id, $dto->ticketTypeUuids)
                );
                $changes[] = 'ticket_type_uuids';
            }

            if (! empty($changes)) {
                event(new EventGateUpdated(
                    eventGateUuid: $eventGate->uuid,
                    actorId: Auth::id(),
                    changes: $changes
                ));
            }

            return $eventGate;
        });
    }

    public function delete(EventGate $eventGate): void
    {
        $this->assertBelongsToCurrentTenant($eventGate);

        DB::transaction(function () use ($eventGate) {
            $this->repository->delete($eventGate);

            event(new EventGateDeleted(
                eventGateUuid: $eventGate->uuid,
                actorId: Auth::id()
            ));
        });
    }

    /**
     * @param  string[]  $uuids
     * @return int[]
     */
    private function resolveTicketTypeIds(int $tenantId, int $eventId, array $uuids): array
    {
        if (empty($uuids)) {
            return [];
        }

        return TicketType::where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereIn('uuid', $uuids)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();
    }

    private function assertBelongsToCurrentTenant(EventGate $eventGate): void
    {
        if ((int) $eventGate->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
