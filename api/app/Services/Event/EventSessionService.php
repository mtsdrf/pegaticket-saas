<?php

namespace App\Services\Event;

use App\DTOs\Event\CreateEventSessionDTO;
use App\DTOs\Event\UpdateEventSessionDTO;
use App\Events\Event\EventSessionCreated;
use App\Events\Event\EventSessionUpdated;
use App\Events\Event\EventSessionDeleted;
use App\Models\Event\Event;
use App\Models\Event\EventSession;
use App\Repositories\Contracts\EventSessionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EventSessionService
{
    public const EAGER_RELATIONS = ['event'];

    public function __construct(
        private EventSessionRepositoryInterface $repository
    ) {
    }

    public function find(EventSession $eventSession): EventSession
    {
        $this->assertBelongsToCurrentTenant($eventSession);

        return $eventSession;
    }

    private const SORTABLE = [
        'starts_at' => 'event_sessions.starts_at',
        'status' => 'event_sessions.status',
    ];

    public function paginate(
        int $tenantId,
        string $eventUuid,
        array $filters = [],
        int $perPage = 15,
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $event = Event::where('uuid', $eventUuid)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $query = EventSession::query()
            ->where('event_sessions.tenant_id', $tenantId)
            ->where('event_sessions.event_id', $event->id)
            ->whereNull('event_sessions.deleted_at')
            ->with(self::EAGER_RELATIONS);

        if (!empty($filters['status'])) {
            $query->where('event_sessions.status', $filters['status']);
        }

        $sortColumn = self::SORTABLE[$sortBy] ?? null;
        $dir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        if ($sortColumn) {
            $query->orderBy($sortColumn, $dir);
        } else {
            $query->orderBy('event_sessions.starts_at');
        }

        return $query->paginate($perPage);
    }

    public function create(CreateEventSessionDTO $dto): EventSession
    {
        return DB::transaction(function () use ($dto) {
            $event = Event::where('uuid', $dto->eventUuid)
                ->where('tenant_id', $dto->tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $eventSession = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'event_id' => $event->id,
                'name' => $dto->name,
                'starts_at' => $dto->startsAt,
                'ends_at' => $dto->endsAt,
                'gate_opens_at' => $dto->gateOpensAt,
                'capacity' => $dto->capacity,
                'status' => $dto->status,
                'sales_start_at' => $dto->salesStartAt,
                'sales_end_at' => $dto->salesEndAt,
            ]);

            event(new EventSessionCreated(
                eventSessionUuid: $eventSession->uuid,
                actorId: Auth::id()
            ));

            return $eventSession;
        });
    }

    public function update(EventSession $eventSession, UpdateEventSessionDTO $dto): EventSession
    {
        $this->assertBelongsToCurrentTenant($eventSession);

        return DB::transaction(function () use ($eventSession, $dto) {
            $original = $eventSession->getOriginal();

            $data = array_filter([
                'name' => $dto->name,
                'starts_at' => $dto->startsAt,
                'ends_at' => $dto->endsAt,
                'gate_opens_at' => $dto->gateOpensAt,
                'capacity' => $dto->capacity,
                'status' => $dto->status,
                'sales_start_at' => $dto->salesStartAt,
                'sales_end_at' => $dto->salesEndAt,
            ], fn($v) => !is_null($v));

            if (!empty($data)) {
                $eventSession = $this->repository->update($eventSession, $data);

                $changes = array_diff_assoc($eventSession->getAttributes(), $original);

                if (!empty($changes)) {
                    event(new EventSessionUpdated(
                        eventSessionUuid: $eventSession->uuid,
                        actorId: Auth::id(),
                        changes: array_keys($changes)
                    ));
                }
            }

            return $eventSession;
        });
    }

    /**
     * Spec 5.4: "uma sessão com vendas não poderá ser removida sem
     * procedimento de cancelamento" — bloqueia soft delete se existir
     * TicketType vinculado a esta sessão com algum item de pedido já
     * vendido (via ticket_types.event_session_id -> sale_items).
     */
    public function delete(EventSession $eventSession): void
    {
        $this->assertBelongsToCurrentTenant($eventSession);

        DB::transaction(function () use ($eventSession) {
            $hasSales = DB::table('sale_items')
                ->join('ticket_types', 'ticket_types.id', '=', 'sale_items.ticket_type_id')
                ->where('ticket_types.event_session_id', $eventSession->id)
                ->whereNull('sale_items.deleted_at')
                ->exists();

            if ($hasSales) {
                abort(422, __('messages.event_session.has_sales'));
            }

            $this->repository->delete($eventSession);

            event(new EventSessionDeleted(
                eventSessionUuid: $eventSession->uuid,
                actorId: Auth::id()
            ));
        });
    }

    private function assertBelongsToCurrentTenant(EventSession $eventSession): void
    {
        if ((int) $eventSession->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
