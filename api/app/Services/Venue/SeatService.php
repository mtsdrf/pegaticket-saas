<?php

namespace App\Services\Venue;

use App\DTOs\Venue\CreateSeatDTO;
use App\DTOs\Venue\UpdateSeatDTO;
use App\Events\Venue\SeatCreated;
use App\Events\Venue\SeatUpdated;
use App\Events\Venue\SeatDeleted;
use App\Models\Venue\Seat;
use App\Models\Venue\Venue;
use App\Repositories\Contracts\SeatRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Seat CRUD sempre opera sobre o draft (VenueMapVersion não publicado)
 * mais recente do Venue — ver VenueService::currentDraftVersion(). Editar
 * um Seat de uma versão já publicada não é permitido (a versão publicada
 * é a "congelada" usada por Event); publicar de novo cria outro draft.
 */
class SeatService
{
    public function __construct(
        private SeatRepositoryInterface $repository,
        private VenueService $venueService
    ) {
    }

    public function find(Seat $seat): Seat
    {
        $this->assertBelongsToCurrentTenant($seat);

        return $seat;
    }

    public function paginate(Venue $venue, array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $this->assertVenueBelongsToCurrentTenant($venue);

        $draft = $this->venueService->currentDraftVersion($venue);

        $query = Seat::query()
            ->where('seats.venue_map_version_id', $draft->id)
            ->whereNull('seats.deleted_at');

        if (!empty($filters['kind'])) {
            $query->where('seats.kind', $filters['kind']);
        }

        if (!empty($filters['sector_name'])) {
            $query->where('seats.sector_name', $filters['sector_name']);
        }

        return $query->orderBy('seats.label')->paginate($perPage);
    }

    public function create(CreateSeatDTO $dto): Seat
    {
        return DB::transaction(function () use ($dto) {
            $venue = Venue::where('uuid', $dto->venueUuid)
                ->where('tenant_id', $dto->tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $draft = $this->venueService->currentDraftVersion($venue);

            $seat = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'venue_map_version_id' => $draft->id,
                'sector_name' => $dto->sectorName,
                'label' => $dto->label,
                'kind' => $dto->kind,
                'capacity' => $dto->capacity,
                'pos_x' => $dto->posX,
                'pos_y' => $dto->posY,
                'width' => $dto->width,
                'height' => $dto->height,
                'geometry_points' => $dto->geometryPoints,
                'is_accessible' => $dto->isAccessible,
                'status' => $dto->status,
            ]);

            event(new SeatCreated(
                seatUuid: $seat->uuid,
                actorId: Auth::id()
            ));

            return $seat;
        });
    }

    public function update(Seat $seat, UpdateSeatDTO $dto): Seat
    {
        $this->assertBelongsToCurrentTenant($seat);
        $this->assertEditable($seat);

        return DB::transaction(function () use ($seat, $dto) {
            $original = $seat->getOriginal();

            $data = array_filter([
                'sector_name' => $dto->sectorName,
                'label' => $dto->label,
                'kind' => $dto->kind,
                'capacity' => $dto->capacity,
                'pos_x' => $dto->posX,
                'pos_y' => $dto->posY,
                'width' => $dto->width,
                'height' => $dto->height,
                'status' => $dto->status,
            ], fn($v) => !is_null($v));

            if ($dto->isAccessible !== null) {
                $data['is_accessible'] = $dto->isAccessible;
            }

            if ($dto->hasWidth) {
                $data['width'] = $dto->width;
            }

            if ($dto->hasHeight) {
                $data['height'] = $dto->height;
            }

            if ($dto->hasGeometryPoints) {
                $data['geometry_points'] = $dto->geometryPoints;
            }

            if (!empty($data)) {
                $seat = $this->repository->update($seat, $data);

                $changes = array_diff_assoc($seat->getAttributes(), $original);

                if (!empty($changes)) {
                    event(new SeatUpdated(
                        seatUuid: $seat->uuid,
                        actorId: Auth::id(),
                        changes: array_keys($changes)
                    ));
                }
            }

            return $seat;
        });
    }

    public function delete(Seat $seat): void
    {
        $this->assertBelongsToCurrentTenant($seat);
        $this->assertEditable($seat);

        DB::transaction(function () use ($seat) {
            $this->repository->delete($seat);

            event(new SeatDeleted(
                seatUuid: $seat->uuid,
                actorId: Auth::id()
            ));
        });
    }

    private function assertEditable(Seat $seat): void
    {
        $seat->loadMissing('mapVersion');

        if ($seat->mapVersion && $seat->mapVersion->is_published) {
            abort(422, __('messages.seat.version_published'));
        }
    }

    private function assertBelongsToCurrentTenant(Seat $seat): void
    {
        if ((int) $seat->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }

    private function assertVenueBelongsToCurrentTenant(Venue $venue): void
    {
        if ((int) $venue->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
