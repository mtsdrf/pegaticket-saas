<?php

namespace App\Services\Balcao;

use App\DTOs\Balcao\CreateTableDTO;
use App\DTOs\Balcao\UpdateTableDTO;
use App\Events\Balcao\TableCreated;
use App\Events\Balcao\TableDeleted;
use App\Events\Balcao\TableUpdated;
use App\Exceptions\DuplicateNameException;
use App\Models\Balcao\Table;
use App\Repositories\Contracts\TableRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TableService
{
    public function __construct(
        private TableRepositoryInterface $repository
    ) {
    }

    public function list(int $tenantId): Collection
    {
        return $this->repository->listForTenant($tenantId);
    }

    public function create(CreateTableDTO $dto): Table
    {
        return DB::transaction(function () use ($dto) {
            if ($this->repository->labelExists($dto->tenantId, $dto->label)) {
                throw new DuplicateNameException(__('messages.table.label_exists'));
            }

            $table = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'label' => $dto->label,
                'area' => $dto->area,
                'seats' => $dto->seats,
                'status' => $dto->status,
            ]);

            event(new TableCreated(
                tableUuid: $table->uuid,
                actorId: (int) Auth::id()
            ));

            return $table;
        });
    }

    public function update(Table $table, UpdateTableDTO $dto): Table
    {
        $this->assertBelongsToCurrentTenant($table);

        return DB::transaction(function () use ($table, $dto) {
            $original = $table->getOriginal();

            if ($dto->label && $this->repository->labelExists((int) $table->tenant_id, $dto->label, $table->id)) {
                throw new DuplicateNameException(__('messages.table.label_exists'));
            }

            $data = array_filter([
                'label' => $dto->label,
                'area' => $dto->area,
                'seats' => $dto->seats,
                'status' => $dto->status,
            ], fn($v) => !is_null($v));

            if (!empty($data)) {
                $table = $this->repository->update($table, $data);

                $changes = array_diff_assoc($table->getAttributes(), $original);

                event(new TableUpdated(
                    tableUuid: $table->uuid,
                    actorId: (int) Auth::id(),
                    changes: array_keys($changes)
                ));
            }

            return $table;
        });
    }

    public function delete(Table $table): void
    {
        $this->assertBelongsToCurrentTenant($table);

        DB::transaction(function () use ($table) {
            $this->repository->delete($table);

            event(new TableDeleted(
                tableUuid: $table->uuid,
                actorId: (int) Auth::id()
            ));
        });
    }

    private function assertBelongsToCurrentTenant(Table $table): void
    {
        if ((int) $table->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
