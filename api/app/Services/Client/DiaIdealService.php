<?php

namespace App\Services\Client;

use App\DTOs\Client\CreateDiaIdealDTO;
use App\DTOs\Client\UpdateDiaIdealDTO;
use App\Events\Client\DiaIdealCreated;
use App\Events\Client\DiaIdealUpdated;
use App\Events\Client\DiaIdealDeleted;
use App\Models\Client\DiaIdeal;
use App\Repositories\Contracts\DiaIdealRepositoryInterface;
use App\Support\GridQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DiaIdealService
{
    public function __construct(
        private DiaIdealRepositoryInterface $repository
    ) {
    }

    public function paginate(
        int $tenantId,
        int $perPage = 15,
        array $filters = [],
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator
    {
        $sortable = [
            'name' => 'dia_ideais.name',
            'is_active' => 'dia_ideais.is_active',
        ];

        $query = DiaIdeal::query()
            ->select(['dia_ideais.id', 'dia_ideais.uuid', 'dia_ideais.name', 'dia_ideais.is_active', 'dia_ideais.created_at'])
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ;

        GridQuery::applyTextFilters($query, $filters, [
            'name' => 'dia_ideais.name',
        ]);

        GridQuery::applyBooleanFilters($query, $filters, [
            'is_active' => 'dia_ideais.is_active',
        ]);

        $sortColumn = is_string($sortBy) ? ($sortable[$sortBy] ?? null) : null;
        $query->orderBy($sortColumn ?? 'dia_ideais.name', GridQuery::normalizeSortDir($sortDir));

        return $query->paginate($perPage);
    }

    public function create(CreateDiaIdealDTO $dto): DiaIdeal
    {
        return DB::transaction(function () use ($dto) {

            if ($this->repository->nameExists($dto->tenantId, $dto->name)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.dia_ideal.name_exists'));
            }

            $diaIdeal = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'name' => $dto->name,
                'is_active' => $dto->isActive,
            ]);

            event(new DiaIdealCreated(
                diaIdealUuid: $diaIdeal->uuid,
                actorId: Auth::id()
            ));

            return $diaIdeal;
        });
    }

    public function update(DiaIdeal $diaIdeal, UpdateDiaIdealDTO $dto): DiaIdeal
    {
        $this->assertBelongsToCurrentTenant($diaIdeal);

        return DB::transaction(function () use ($diaIdeal, $dto) {

            $original = $diaIdeal->getOriginal();

            if ($dto->name && $this->repository->nameExists($diaIdeal->tenant_id, $dto->name, $diaIdeal->id)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.dia_ideal.name_exists'));
            }

            $data = array_filter([
                'name' => $dto->name,
                'is_active' => $dto->isActive,
            ], fn($v) => !is_null($v));

            if (!empty($data)) {
                $diaIdeal = $this->repository->update($diaIdeal, $data);

                $changes = array_diff_assoc($diaIdeal->getAttributes(), $original);

                event(new DiaIdealUpdated(
                    diaIdealUuid: $diaIdeal->uuid,
                    actorId: Auth::id(),
                    changes: array_keys($changes)
                ));
            }

            return $diaIdeal;
        });
    }

    public function delete(DiaIdeal $diaIdeal): void
    {
        $this->assertBelongsToCurrentTenant($diaIdeal);

        DB::transaction(function () use ($diaIdeal) {
            $this->repository->delete($diaIdeal);

            event(new DiaIdealDeleted(
                diaIdealUuid: $diaIdeal->uuid,
                actorId: Auth::id()
            ));
        });
    }

    /**
     * Route-model-binding resolve só por uuid, sem escopo de tenant — sem
     * esta checagem, um usuário com permissão poderia mutar registro de
     * outro tenant só sabendo o uuid (IDOR).
     */
    private function assertBelongsToCurrentTenant(DiaIdeal $diaIdeal): void
    {
        if ((int) $diaIdeal->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
