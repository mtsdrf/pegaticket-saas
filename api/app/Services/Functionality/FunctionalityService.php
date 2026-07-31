<?php

namespace App\Services\Functionality;

use App\DTOs\Functionality\{CreateFunctionalityDTO, UpdateFunctionalityDTO};
use App\Events\Functionality\{
    FunctionalityCreated,
    FunctionalityUpdated,
    FunctionalityDeleted
};
use App\Models\Functionality\Functionality;
use App\Repositories\Contracts\FunctionalityRepositoryInterface;
use App\Support\GridQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Class FunctionalityService
 * 
 * Camada de serviço para Functionality.
 * Orquestra regras de negócio e delega persistência ao Repository.
 */
class FunctionalityService
{
    /**
     * @var FunctionalityRepositoryInterface
     */
    protected FunctionalityRepositoryInterface $repository;

    /**
     * FunctionalityService constructor.
     * 
     * @param FunctionalityRepositoryInterface $repository
     */
    public function __construct(FunctionalityRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Listar funcionalidades paginadas
     * 
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function paginate(
        int $perPage = 15,
        array $filters = [],
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator
    {
        $sortable = [
            'name' => 'functionalities.name',
            'slug' => 'functionalities.slug',
            'description' => 'functionalities.description',
            'is_active' => 'functionalities.is_active',
        ];

        $query = Functionality::query()
            ->select([
                'functionalities.id',
                'functionalities.uuid',
                'functionalities.name',
                'functionalities.slug',
                'functionalities.description',
                'functionalities.is_active',
                'functionalities.created_at',
                'functionalities.updated_at',
            ])
            ->whereNull('functionalities.deleted_at');

        GridQuery::applyTextFilters($query, $filters, [
            'name' => 'functionalities.name',
            'slug' => 'functionalities.slug',
            'description' => 'functionalities.description',
        ]);

        GridQuery::applyBooleanFilters($query, $filters, [
            'is_active' => 'functionalities.is_active',
        ]);

        $sortColumn = is_string($sortBy) ? ($sortable[$sortBy] ?? null) : null;
        $query->orderBy($sortColumn ?? 'functionalities.name', GridQuery::normalizeSortDir($sortDir));

        return $query->paginate($perPage);
    }

    /**
     * Criar nova funcionalidade
     * 
     * @param CreateFunctionalityDTO $dto
     * @return Functionality
     */
    public function create(CreateFunctionalityDTO $dto): Functionality
    {
        return DB::transaction(function () use ($dto) {
            $functionality = $this->repository->create([
                'name' => $dto->name,
                'slug' => $dto->slug,
                'description' => $dto->description,
                'is_active' => $dto->isActive,
            ]);

            event(new FunctionalityCreated(
                functionalityUuid: $functionality->uuid,
                actorId: Auth::id()
            ));

            return $functionality;
        });
    }

    /**
     * Atualizar funcionalidade
     * 
     * @param Functionality $functionality
     * @param UpdateFunctionalityDTO $dto
     * @return Functionality
     */
    public function update(Functionality $functionality, UpdateFunctionalityDTO $dto): Functionality
    {
        return DB::transaction(function () use ($functionality, $dto) {
            $original = $functionality->getOriginal();

            $data = array_filter([
                'name' => $dto->name,
                'slug' => $dto->slug,
                'description' => $dto->description,
                'is_active' => $dto->isActive,
            ], fn($v) => !is_null($v));

            if (!empty($data)) {
                $functionality = $this->repository->update($functionality, $data);

                $changes = array_diff_assoc($functionality->getAttributes(), $original);

                event(new FunctionalityUpdated(
                    functionalityUuid: $functionality->uuid,
                    actorId: Auth::id(),
                    changes: array_keys($changes)
                ));
            }

            return $functionality;
        });
    }

    /**
     * Deletar funcionalidade (soft delete)
     * 
     * @param Functionality $functionality
     * @return void
     */
    public function delete(Functionality $functionality): void
    {
        DB::transaction(function () use ($functionality) {
            $this->repository->delete($functionality);

            event(new FunctionalityDeleted(
                functionalityUuid: $functionality->uuid,
                actorId: Auth::id()
            ));
        });
    }
}
