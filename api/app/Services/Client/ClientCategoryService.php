<?php

namespace App\Services\Client;

use App\DTOs\Client\CreateClientCategoryDTO;
use App\DTOs\Client\UpdateClientCategoryDTO;
use App\Events\Client\ClientCategoryCreated;
use App\Events\Client\ClientCategoryUpdated;
use App\Events\Client\ClientCategoryDeleted;
use App\Models\Client\ClientCategory;
use App\Repositories\Contracts\ClientCategoryRepositoryInterface;
use App\Support\GridQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClientCategoryService
{
    public function __construct(
        private ClientCategoryRepositoryInterface $repository
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
            'name' => 'client_categories.name',
            'is_active' => 'client_categories.is_active',
        ];

        $query = ClientCategory::query()
            ->select(['client_categories.id', 'client_categories.uuid', 'client_categories.name', 'client_categories.is_active', 'client_categories.created_at'])
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ;

        GridQuery::applyTextFilters($query, $filters, [
            'name' => 'client_categories.name',
        ]);

        GridQuery::applyBooleanFilters($query, $filters, [
            'is_active' => 'client_categories.is_active',
        ]);

        $sortColumn = is_string($sortBy) ? ($sortable[$sortBy] ?? null) : null;
        $query->orderBy($sortColumn ?? 'client_categories.name', GridQuery::normalizeSortDir($sortDir));

        return $query->paginate($perPage);
    }

    public function create(CreateClientCategoryDTO $dto): ClientCategory
    {
        return DB::transaction(function () use ($dto) {

            if ($this->repository->nameExists($dto->tenantId, $dto->name)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.client_category.name_exists'));
            }

            $category = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'name' => $dto->name,
                'is_active' => $dto->isActive,
            ]);

            event(new ClientCategoryCreated(
                clientCategoryUuid: $category->uuid,
                actorId: Auth::id()
            ));

            return $category;
        });
    }

    public function update(ClientCategory $category, UpdateClientCategoryDTO $dto): ClientCategory
    {
        $this->assertBelongsToCurrentTenant($category);

        return DB::transaction(function () use ($category, $dto) {

            $original = $category->getOriginal();

            if ($dto->name && $this->repository->nameExists($category->tenant_id, $dto->name, $category->id)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.client_category.name_exists'));
            }

            $data = array_filter([
                'name' => $dto->name,
                'is_active' => $dto->isActive,
            ], fn($v) => !is_null($v));

            if (!empty($data)) {
                $category = $this->repository->update($category, $data);

                $changes = array_diff_assoc($category->getAttributes(), $original);

                event(new ClientCategoryUpdated(
                    clientCategoryUuid: $category->uuid,
                    actorId: Auth::id(),
                    changes: array_keys($changes)
                ));
            }

            return $category;
        });
    }

    public function delete(ClientCategory $category): void
    {
        $this->assertBelongsToCurrentTenant($category);

        DB::transaction(function () use ($category) {
            $this->repository->delete($category);

            event(new ClientCategoryDeleted(
                clientCategoryUuid: $category->uuid,
                actorId: Auth::id()
            ));
        });
    }

    /**
     * Route-model-binding resolve só por uuid, sem escopo de tenant — sem
     * esta checagem, um usuário com permissão poderia mutar registro de
     * outro tenant só sabendo o uuid (IDOR).
     */
    private function assertBelongsToCurrentTenant(ClientCategory $category): void
    {
        if ((int) $category->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
