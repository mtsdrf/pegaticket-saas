<?php

namespace App\Services\Location;

use App\DTOs\Location\CreateEstadoDTO;
use App\DTOs\Location\UpdateEstadoDTO;
use App\Events\Location\EstadoCreated;
use App\Events\Location\EstadoUpdated;
use App\Events\Location\EstadoDeleted;
use App\Models\Location\Estado;
use App\Repositories\Contracts\EstadoRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EstadoService
{
    public function __construct(
        private EstadoRepositoryInterface $repository
    ) {
    }

    private const SORTABLE = [
        'name' => 'name',
        'uf' => 'uf',
        'is_active' => 'is_active',
    ];

    public function paginate(
        int $perPage = 15,
        array $filters = [],
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $query = Estado::whereNull('deleted_at');

        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['uf'])) {
            $query->where('uf', 'like', '%' . $filters['uf'] . '%');
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortColumn = self::SORTABLE[$sortBy] ?? null;
        $dir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        if ($sortColumn) {
            $query->orderBy($sortColumn, $dir);
        } else {
            $query->orderBy('name');
        }

        return $query->paginate($perPage);
    }

    public function create(CreateEstadoDTO $dto): Estado
    {
        return DB::transaction(function () use ($dto) {

            if ($this->repository->ufExists($dto->uf)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.estado.uf_exists'));
            }

            $estado = $this->repository->create([
                'name' => $dto->name,
                'uf' => $dto->uf,
                'is_active' => $dto->isActive,
            ]);

            event(new EstadoCreated(
                estadoUuid: $estado->uuid,
                actorId: Auth::id()
            ));

            return $estado;
        });
    }

    public function update(Estado $estado, UpdateEstadoDTO $dto): Estado
    {
        return DB::transaction(function () use ($estado, $dto) {

            $original = $estado->getOriginal();

            if ($dto->uf && $this->repository->ufExists($dto->uf, $estado->id)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.estado.uf_exists'));
            }

            $data = array_filter([
                'name' => $dto->name,
                'uf' => $dto->uf,
                'is_active' => $dto->isActive,
            ], fn($v) => !is_null($v));

            if (!empty($data)) {
                $estado = $this->repository->update($estado, $data);

                $changes = array_diff_assoc($estado->getAttributes(), $original);

                event(new EstadoUpdated(
                    estadoUuid: $estado->uuid,
                    actorId: Auth::id(),
                    changes: array_keys($changes)
                ));
            }

            return $estado;
        });
    }

    public function delete(Estado $estado): void
    {
        DB::transaction(function () use ($estado) {
            $this->repository->delete($estado);

            event(new EstadoDeleted(
                estadoUuid: $estado->uuid,
                actorId: Auth::id()
            ));
        });
    }
}
