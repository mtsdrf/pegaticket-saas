<?php

namespace App\Services\Product;

use App\DTOs\Product\CreateProductCategoryDTO;
use App\DTOs\Product\UpdateProductCategoryDTO;
use App\Events\Product\ProductCategoryCreated;
use App\Events\Product\ProductCategoryUpdated;
use App\Events\Product\ProductCategoryDeleted;
use App\Models\Product\ProductCategory;
use App\Repositories\Contracts\ProductCategoryRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductCategoryService
{
    public function __construct(
        private ProductCategoryRepositoryInterface $repository
    ) {
    }

    private const SORTABLE = [
        'name' => 'name',
        'priority' => 'priority',
        'is_active' => 'is_active',
    ];

    public function paginate(
        int $tenantId,
        array $filters = [],
        int $perPage = 15,
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $query = ProductCategory::where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (array_key_exists('priority_min', $filters) && $filters['priority_min'] !== null && $filters['priority_min'] !== '') {
            $query->where('priority', '>=', $filters['priority_min']);
        }

        if (array_key_exists('priority_max', $filters) && $filters['priority_max'] !== null && $filters['priority_max'] !== '') {
            $query->where('priority', '<=', $filters['priority_max']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        // Busca global (campo único acima do grid) — recurso só tem uma
        // coluna de texto, mas mantém o mesmo padrão de closure agrupado
        // dos outros recursos por consistência de contrato.
        if (!empty($filters['q'])) {
            $term = $filters['q'];
            $query->where(function ($sub) use ($term) {
                $sub->where('name', 'like', '%' . $term . '%');
            });
        }

        $sortColumn = self::SORTABLE[$sortBy] ?? null;
        $dir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        if ($sortColumn) {
            $query->orderBy($sortColumn, $dir);
        } else {
            $query->orderBy('priority')->orderBy('name');
        }

        return $query->paginate($perPage);
    }

    public function create(CreateProductCategoryDTO $dto): ProductCategory
    {
        return DB::transaction(function () use ($dto) {

            if ($this->repository->nameExists($dto->tenantId, $dto->name)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.product_category.name_exists'));
            }

            $category = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'name' => $dto->name,
                'priority' => $dto->priority,
                'is_active' => $dto->isActive,
            ]);

            event(new ProductCategoryCreated(
                productCategoryUuid: $category->uuid,
                actorId: Auth::id()
            ));

            return $category;
        });
    }

    public function update(ProductCategory $category, UpdateProductCategoryDTO $dto): ProductCategory
    {
        $this->assertBelongsToCurrentTenant($category);

        return DB::transaction(function () use ($category, $dto) {

            $original = $category->getOriginal();

            if ($dto->name && $this->repository->nameExists($category->tenant_id, $dto->name, $category->id)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.product_category.name_exists'));
            }

            $data = array_filter([
                'name' => $dto->name,
                'priority' => $dto->priority,
                'is_active' => $dto->isActive,
            ], fn($v) => !is_null($v));

            if (!empty($data)) {
                $category = $this->repository->update($category, $data);

                $changes = array_diff_assoc($category->getAttributes(), $original);

                event(new ProductCategoryUpdated(
                    productCategoryUuid: $category->uuid,
                    actorId: Auth::id(),
                    changes: array_keys($changes)
                ));
            }

            return $category;
        });
    }

    public function delete(ProductCategory $category): void
    {
        $this->assertBelongsToCurrentTenant($category);

        DB::transaction(function () use ($category) {
            $this->repository->delete($category);

            event(new ProductCategoryDeleted(
                productCategoryUuid: $category->uuid,
                actorId: Auth::id()
            ));
        });
    }

    /**
     * Route-model-binding resolve só por uuid, sem escopo de tenant — sem
     * esta checagem, um usuário com permissão poderia mutar registro de
     * outro tenant só sabendo o uuid (IDOR).
     */
    private function assertBelongsToCurrentTenant(ProductCategory $category): void
    {
        if ((int) $category->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
