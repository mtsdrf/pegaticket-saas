<?php

namespace App\Services\Client;

use App\DTOs\Client\CreatePeriodoIdealDTO;
use App\DTOs\Client\UpdatePeriodoIdealDTO;
use App\Events\Client\PeriodoIdealCreated;
use App\Events\Client\PeriodoIdealUpdated;
use App\Events\Client\PeriodoIdealDeleted;
use App\Models\Client\PeriodoIdeal;
use App\Repositories\Contracts\PeriodoIdealRepositoryInterface;
use App\Support\GridQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PeriodoIdealService
{
    public function __construct(
        private PeriodoIdealRepositoryInterface $repository
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
            'name' => 'periodo_ideais.name',
            'is_active' => 'periodo_ideais.is_active',
        ];

        $query = PeriodoIdeal::query()
            ->select(['periodo_ideais.id', 'periodo_ideais.uuid', 'periodo_ideais.name', 'periodo_ideais.is_active', 'periodo_ideais.created_at'])
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ;

        GridQuery::applyTextFilters($query, $filters, [
            'name' => 'periodo_ideais.name',
        ]);

        GridQuery::applyBooleanFilters($query, $filters, [
            'is_active' => 'periodo_ideais.is_active',
        ]);

        $sortColumn = is_string($sortBy) ? ($sortable[$sortBy] ?? null) : null;
        $query->orderBy($sortColumn ?? 'periodo_ideais.name', GridQuery::normalizeSortDir($sortDir));

        return $query->paginate($perPage);
    }

    public function create(CreatePeriodoIdealDTO $dto): PeriodoIdeal
    {
        return DB::transaction(function () use ($dto) {

            if ($this->repository->nameExists($dto->tenantId, $dto->name)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.periodo_ideal.name_exists'));
            }

            $periodoIdeal = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'name' => $dto->name,
                'is_active' => $dto->isActive,
            ]);

            event(new PeriodoIdealCreated(
                periodoIdealUuid: $periodoIdeal->uuid,
                actorId: Auth::id()
            ));

            return $periodoIdeal;
        });
    }

    public function update(PeriodoIdeal $periodoIdeal, UpdatePeriodoIdealDTO $dto): PeriodoIdeal
    {
        $this->assertBelongsToCurrentTenant($periodoIdeal);

        return DB::transaction(function () use ($periodoIdeal, $dto) {

            $original = $periodoIdeal->getOriginal();

            if ($dto->name && $this->repository->nameExists($periodoIdeal->tenant_id, $dto->name, $periodoIdeal->id)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.periodo_ideal.name_exists'));
            }

            $data = array_filter([
                'name' => $dto->name,
                'is_active' => $dto->isActive,
            ], fn($v) => !is_null($v));

            if (!empty($data)) {
                $periodoIdeal = $this->repository->update($periodoIdeal, $data);

                $changes = array_diff_assoc($periodoIdeal->getAttributes(), $original);

                event(new PeriodoIdealUpdated(
                    periodoIdealUuid: $periodoIdeal->uuid,
                    actorId: Auth::id(),
                    changes: array_keys($changes)
                ));
            }

            return $periodoIdeal;
        });
    }

    public function delete(PeriodoIdeal $periodoIdeal): void
    {
        $this->assertBelongsToCurrentTenant($periodoIdeal);

        DB::transaction(function () use ($periodoIdeal) {
            $this->repository->delete($periodoIdeal);

            event(new PeriodoIdealDeleted(
                periodoIdealUuid: $periodoIdeal->uuid,
                actorId: Auth::id()
            ));
        });
    }

    /**
     * Route-model-binding resolve só por uuid, sem escopo de tenant — sem
     * esta checagem, um usuário com permissão poderia mutar registro de
     * outro tenant só sabendo o uuid (IDOR).
     */
    private function assertBelongsToCurrentTenant(PeriodoIdeal $periodoIdeal): void
    {
        if ((int) $periodoIdeal->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
