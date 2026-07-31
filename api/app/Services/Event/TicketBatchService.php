<?php

namespace App\Services\Event;

use App\DTOs\Event\CreateTicketBatchDTO;
use App\DTOs\Event\UpdateTicketBatchDTO;
use App\Events\Event\TicketBatchCreated;
use App\Events\Event\TicketBatchUpdated;
use App\Events\Event\TicketBatchDeleted;
use App\Models\Event\TicketBatch;
use App\Models\Event\TicketType;
use App\Repositories\Contracts\TicketBatchRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TicketBatchService
{
    public const EAGER_RELATIONS = ['ticketType'];

    public function __construct(
        private TicketBatchRepositoryInterface $repository
    ) {
    }

    public function find(TicketBatch $ticketBatch): TicketBatch
    {
        $this->assertBelongsToCurrentTenant($ticketBatch);

        return $ticketBatch;
    }

    private const SORTABLE = [
        'name' => 'ticket_batches.name',
        'priority' => 'ticket_batches.priority',
        'status' => 'ticket_batches.status',
    ];

    public function paginate(
        int $tenantId,
        string $ticketTypeUuid,
        array $filters = [],
        int $perPage = 15,
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $ticketType = TicketType::where('uuid', $ticketTypeUuid)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $query = TicketBatch::query()
            ->where('ticket_batches.tenant_id', $tenantId)
            ->where('ticket_batches.ticket_type_id', $ticketType->id)
            ->whereNull('ticket_batches.deleted_at')
            ->with(self::EAGER_RELATIONS);

        if (!empty($filters['status'])) {
            $query->where('ticket_batches.status', $filters['status']);
        }

        $sortColumn = self::SORTABLE[$sortBy] ?? null;
        $dir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        if ($sortColumn) {
            $query->orderBy($sortColumn, $dir);
        } else {
            $query->orderBy('ticket_batches.priority');
        }

        return $query->paginate($perPage);
    }

    public function create(CreateTicketBatchDTO $dto): TicketBatch
    {
        return DB::transaction(function () use ($dto) {
            $ticketType = TicketType::where('uuid', $dto->ticketTypeUuid)
                ->where('tenant_id', $dto->tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $ticketBatch = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'ticket_type_id' => $ticketType->id,
                'name' => $dto->name,
                'price' => $dto->price,
                'quantity' => $dto->quantity,
                'quantity_sold' => 0,
                'starts_at' => $dto->startsAt,
                'ends_at' => $dto->endsAt,
                'priority' => $dto->priority,
                'auto_advance' => $dto->autoAdvance,
                'status' => $dto->status,
            ]);

            event(new TicketBatchCreated(
                ticketBatchUuid: $ticketBatch->uuid,
                actorId: Auth::id()
            ));

            return $ticketBatch;
        });
    }

    public function update(TicketBatch $ticketBatch, UpdateTicketBatchDTO $dto): TicketBatch
    {
        $this->assertBelongsToCurrentTenant($ticketBatch);

        return DB::transaction(function () use ($ticketBatch, $dto) {
            $original = $ticketBatch->getOriginal();

            $data = array_filter([
                'name' => $dto->name,
                'price' => $dto->price,
                'quantity' => $dto->quantity,
                'starts_at' => $dto->startsAt,
                'ends_at' => $dto->endsAt,
                'priority' => $dto->priority,
                'auto_advance' => $dto->autoAdvance,
                'status' => $dto->status,
            ], fn($v) => !is_null($v));

            if (!empty($data)) {
                $ticketBatch = $this->repository->update($ticketBatch, $data);

                $changes = array_diff_assoc($ticketBatch->getAttributes(), $original);

                if (!empty($changes)) {
                    event(new TicketBatchUpdated(
                        ticketBatchUuid: $ticketBatch->uuid,
                        actorId: Auth::id(),
                        changes: array_keys($changes)
                    ));
                }
            }

            return $ticketBatch;
        });
    }

    public function delete(TicketBatch $ticketBatch): void
    {
        $this->assertBelongsToCurrentTenant($ticketBatch);

        DB::transaction(function () use ($ticketBatch) {
            if ($ticketBatch->quantity_sold > 0) {
                abort(422, __('messages.ticket_batch.has_sales'));
            }

            $this->repository->delete($ticketBatch);

            event(new TicketBatchDeleted(
                ticketBatchUuid: $ticketBatch->uuid,
                actorId: Auth::id()
            ));
        });
    }

    private function assertBelongsToCurrentTenant(TicketBatch $ticketBatch): void
    {
        if ((int) $ticketBatch->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
