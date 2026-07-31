<?php

namespace App\Services\Location;

use App\DTOs\Location\CreateBairroDTO;
use App\DTOs\Location\UpdateBairroDTO;
use App\Events\Location\BairroCreated;
use App\Events\Location\BairroUpdated;
use App\Events\Location\BairroDeleted;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Repositories\Contracts\BairroRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BairroService
{
    public function __construct(
        private BairroRepositoryInterface $repository
    ) {
    }

    /**
     * Whitelist de sort_by aceito pelo grid — cidade_name exige leftJoin
     * (bairro belongsTo cidade, 1:1, não duplica linhas). select('bairros.*')
     * evita ambiguidade de coluna ("name" existe nas duas tabelas).
     */
    private const SORTABLE = [
        'name' => 'bairros.name',
        'cidade_name' => 'cidades.name',
        'is_active' => 'bairros.is_active',
    ];

    public function paginate(
        int $perPage = 15,
        ?string $cidadeUuid = null,
        array $filters = [],
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $sortColumn = self::SORTABLE[$sortBy] ?? null;
        $needsCidadeJoin = $sortColumn === 'cidades.name' || !empty($filters['cidade_name']);

        $query = Bairro::query()
            ->select('bairros.*')
            ->whereNull('bairros.deleted_at')
            ->with('cidade');

        if ($needsCidadeJoin) {
            $query->leftJoin('cidades', 'cidades.id', '=', 'bairros.cidade_id');
        }

        // Filtro exato já existente — cascade do dropdown Bairro no
        // formulário de Cliente/Endereco. Não remover/renomear.
        if ($cidadeUuid) {
            $query->whereHas('cidade', fn($q) => $q->where('uuid', $cidadeUuid));
        }

        if (!empty($filters['name'])) {
            $query->where('bairros.name', 'like', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['cidade_name'])) {
            $query->where('cidades.name', 'like', '%' . $filters['cidade_name'] . '%');
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('bairros.is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $dir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        if ($sortColumn) {
            $query->orderBy($sortColumn, $dir);
        } else {
            $query->orderBy('bairros.name');
        }

        return $query->paginate($perPage);
    }

    public function create(CreateBairroDTO $dto): Bairro
    {
        return DB::transaction(function () use ($dto) {

            $cidade = Cidade::where('uuid', $dto->cidadeUuid)
                ->whereNull('deleted_at')
                ->firstOrFail();

            if ($this->repository->nameExists($cidade->id, $dto->name)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.bairro.name_exists'));
            }

            $bairro = $this->repository->create([
                'cidade_id' => $cidade->id,
                'name' => $dto->name,
                'is_active' => $dto->isActive,
            ]);

            event(new BairroCreated(
                bairroUuid: $bairro->uuid,
                actorId: Auth::id()
            ));

            return $bairro;
        });
    }

    public function update(Bairro $bairro, UpdateBairroDTO $dto): Bairro
    {
        return DB::transaction(function () use ($bairro, $dto) {

            $original = $bairro->getOriginal();

            $cidadeId = $bairro->cidade_id;

            if ($dto->cidadeUuid) {
                $cidade = Cidade::where('uuid', $dto->cidadeUuid)
                    ->whereNull('deleted_at')
                    ->firstOrFail();

                $cidadeId = $cidade->id;
            }

            if ($dto->name && $this->repository->nameExists($cidadeId, $dto->name, $bairro->id)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.bairro.name_exists'));
            }

            $data = array_filter([
                'name' => $dto->name,
                'is_active' => $dto->isActive,
            ], fn($v) => !is_null($v));

            if ($dto->cidadeUuid) {
                $data['cidade_id'] = $cidadeId;
            }

            if (!empty($data)) {
                $bairro = $this->repository->update($bairro, $data);

                $changes = array_diff_assoc($bairro->getAttributes(), $original);

                event(new BairroUpdated(
                    bairroUuid: $bairro->uuid,
                    actorId: Auth::id(),
                    changes: array_keys($changes)
                ));
            }

            return $bairro;
        });
    }

    public function delete(Bairro $bairro): void
    {
        DB::transaction(function () use ($bairro) {
            $this->repository->delete($bairro);

            event(new BairroDeleted(
                bairroUuid: $bairro->uuid,
                actorId: Auth::id()
            ));
        });
    }
}
