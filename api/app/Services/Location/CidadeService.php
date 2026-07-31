<?php

namespace App\Services\Location;

use App\DTOs\Location\CreateCidadeDTO;
use App\DTOs\Location\UpdateCidadeDTO;
use App\Events\Location\CidadeCreated;
use App\Events\Location\CidadeUpdated;
use App\Events\Location\CidadeDeleted;
use App\Models\Location\Cidade;
use App\Models\Location\Estado;
use App\Repositories\Contracts\CidadeRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CidadeService
{
    public function __construct(
        private CidadeRepositoryInterface $repository
    ) {
    }

    /**
     * Whitelist de sort_by aceito pelo grid — estado_name exige leftJoin
     * (cidade belongsTo estado, 1:1, não duplica linhas). select('cidades.*')
     * evita ambiguidade de coluna ("name" existe nas duas tabelas).
     */
    private const SORTABLE = [
        'name' => 'cidades.name',
        'estado_name' => 'estados.name',
        'is_active' => 'cidades.is_active',
    ];

    public function paginate(
        int $perPage = 15,
        ?string $estadoUuid = null,
        array $filters = [],
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $sortColumn = self::SORTABLE[$sortBy] ?? null;
        $needsEstadoJoin = $sortColumn === 'estados.name' || !empty($filters['estado_name']);

        $query = Cidade::query()
            ->select('cidades.*')
            ->whereNull('cidades.deleted_at')
            ->with('estado');

        if ($needsEstadoJoin) {
            $query->leftJoin('estados', 'estados.id', '=', 'cidades.estado_id');
        }

        // Filtro exato já existente — cascade do dropdown Cidade no
        // formulário de Cliente/Endereco. Não remover/renomear.
        if ($estadoUuid) {
            $query->whereHas('estado', fn($q) => $q->where('uuid', $estadoUuid));
        }

        if (!empty($filters['name'])) {
            $query->where('cidades.name', 'like', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['estado_name'])) {
            $query->where('estados.name', 'like', '%' . $filters['estado_name'] . '%');
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('cidades.is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $dir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        if ($sortColumn) {
            $query->orderBy($sortColumn, $dir);
        } else {
            $query->orderBy('cidades.name');
        }

        return $query->paginate($perPage);
    }

    public function create(CreateCidadeDTO $dto): Cidade
    {
        return DB::transaction(function () use ($dto) {

            $estado = Estado::where('uuid', $dto->estadoUuid)
                ->whereNull('deleted_at')
                ->firstOrFail();

            if ($this->repository->nameExists($estado->id, $dto->name)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.cidade.name_exists'));
            }

            $cidade = $this->repository->create([
                'estado_id' => $estado->id,
                'name' => $dto->name,
                'is_active' => $dto->isActive,
            ]);

            event(new CidadeCreated(
                cidadeUuid: $cidade->uuid,
                actorId: Auth::id()
            ));

            return $cidade;
        });
    }

    public function update(Cidade $cidade, UpdateCidadeDTO $dto): Cidade
    {
        return DB::transaction(function () use ($cidade, $dto) {

            $original = $cidade->getOriginal();

            $estadoId = $cidade->estado_id;

            if ($dto->estadoUuid) {
                $estado = Estado::where('uuid', $dto->estadoUuid)
                    ->whereNull('deleted_at')
                    ->firstOrFail();

                $estadoId = $estado->id;
            }

            if ($dto->name && $this->repository->nameExists($estadoId, $dto->name, $cidade->id)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.cidade.name_exists'));
            }

            $data = array_filter([
                'name' => $dto->name,
                'is_active' => $dto->isActive,
            ], fn($v) => !is_null($v));

            if ($dto->estadoUuid) {
                $data['estado_id'] = $estadoId;
            }

            if (!empty($data)) {
                $cidade = $this->repository->update($cidade, $data);

                $changes = array_diff_assoc($cidade->getAttributes(), $original);

                event(new CidadeUpdated(
                    cidadeUuid: $cidade->uuid,
                    actorId: Auth::id(),
                    changes: array_keys($changes)
                ));
            }

            return $cidade;
        });
    }

    public function delete(Cidade $cidade): void
    {
        DB::transaction(function () use ($cidade) {
            $this->repository->delete($cidade);

            event(new CidadeDeleted(
                cidadeUuid: $cidade->uuid,
                actorId: Auth::id()
            ));
        });
    }
}
