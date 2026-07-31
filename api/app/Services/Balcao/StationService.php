<?php

namespace App\Services\Balcao;

use App\DTOs\Balcao\CreateStationDTO;
use App\DTOs\Balcao\UpdateStationDTO;
use App\Events\Balcao\StationCreated;
use App\Events\Balcao\StationDeleted;
use App\Events\Balcao\StationUpdated;
use App\Exceptions\DuplicateNameException;
use App\Models\Balcao\Station;
use App\Repositories\Contracts\StationRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StationService
{
    public function __construct(
        private StationRepositoryInterface $repository
    ) {
    }

    public function list(int $tenantId): Collection
    {
        return $this->repository->listForTenant($tenantId);
    }

    public function create(CreateStationDTO $dto): Station
    {
        return DB::transaction(function () use ($dto) {
            if ($this->repository->nameExists($dto->tenantId, $dto->name)) {
                throw new DuplicateNameException(__('messages.station.name_exists'));
            }

            $station = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'name' => $dto->name,
                'type' => $dto->type,
                'is_active' => $dto->isActive,
            ]);

            event(new StationCreated(
                stationUuid: $station->uuid,
                actorId: (int) Auth::id()
            ));

            return $station;
        });
    }

    public function update(Station $station, UpdateStationDTO $dto): Station
    {
        $this->assertBelongsToCurrentTenant($station);

        return DB::transaction(function () use ($station, $dto) {
            $original = $station->getOriginal();

            if ($dto->name && $this->repository->nameExists((int) $station->tenant_id, $dto->name, $station->id)) {
                throw new DuplicateNameException(__('messages.station.name_exists'));
            }

            $data = array_filter([
                'name' => $dto->name,
                'type' => $dto->type,
                'is_active' => $dto->isActive,
            ], fn($v) => !is_null($v));

            if (!empty($data)) {
                $station = $this->repository->update($station, $data);

                $changes = array_diff_assoc($station->getAttributes(), $original);

                event(new StationUpdated(
                    stationUuid: $station->uuid,
                    actorId: (int) Auth::id(),
                    changes: array_keys($changes)
                ));
            }

            return $station;
        });
    }

    public function delete(Station $station): void
    {
        $this->assertBelongsToCurrentTenant($station);

        DB::transaction(function () use ($station) {
            $this->repository->delete($station);

            event(new StationDeleted(
                stationUuid: $station->uuid,
                actorId: (int) Auth::id()
            ));
        });
    }

    private function assertBelongsToCurrentTenant(Station $station): void
    {
        if ((int) $station->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
