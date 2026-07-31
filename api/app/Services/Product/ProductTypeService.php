<?php

namespace App\Services\Product;

use App\DTOs\Product\CreateProductTypeDTO;
use App\DTOs\Product\UpdateProductTypeDTO;
use App\Events\Product\ProductTypeCreated;
use App\Events\Product\ProductTypeUpdated;
use App\Events\Product\ProductTypeDeleted;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use App\Repositories\Contracts\ProductTypeRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductTypeService
{
    public function __construct(
        private ProductTypeRepositoryInterface $repository
    ) {
    }

    /**
     * Whitelist de sort_by aceito pelo grid — product_category_name exige
     * leftJoin (product_type belongsTo product_category, 1:1, não duplica
     * linhas). select('product_types.*') evita ambiguidade de coluna (ex.
     * "name") entre as duas tabelas.
     */
    private const SORTABLE = [
        'name' => 'product_types.name',
        'product_category_name' => 'product_categories.name',
        'priority' => 'product_types.priority',
        'is_active' => 'product_types.is_active',
    ];

    public function paginate(
        int $tenantId,
        array $filters = [],
        int $perPage = 15,
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $sortColumn = self::SORTABLE[$sortBy] ?? null;
        $needsCategoryJoin = $sortColumn === 'product_categories.name' || !empty($filters['product_category_name']);

        $query = ProductType::query()
            ->select('product_types.*')
            ->where('product_types.tenant_id', $tenantId)
            ->whereNull('product_types.deleted_at')
            ->with('productCategory');

        if ($needsCategoryJoin) {
            $query->leftJoin('product_categories', 'product_categories.id', '=', 'product_types.product_category_id');
        }

        if (!empty($filters['name'])) {
            $query->where('product_types.name', 'like', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['product_category_name'])) {
            $query->where('product_categories.name', 'like', '%' . $filters['product_category_name'] . '%');
        }

        if (array_key_exists('priority_min', $filters) && $filters['priority_min'] !== null && $filters['priority_min'] !== '') {
            $query->where('product_types.priority', '>=', $filters['priority_min']);
        }

        if (array_key_exists('priority_max', $filters) && $filters['priority_max'] !== null && $filters['priority_max'] !== '') {
            $query->where('product_types.priority', '<=', $filters['priority_max']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('product_types.is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        // Busca global (campo único acima do grid) — OR entre as colunas
        // buscáveis, agrupado num closure próprio para não virar AND com os
        // filtros por coluna acima. Usa whereHas (subquery própria), não
        // depende do leftJoin condicional usado pelo sort_by.
        if (!empty($filters['q'])) {
            $term = $filters['q'];
            $query->where(function ($sub) use ($term) {
                $sub->where('product_types.name', 'like', '%' . $term . '%')
                    ->orWhereHas('productCategory', fn($r) => $r->where('name', 'like', '%' . $term . '%'));
            });
        }

        $dir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        if ($sortColumn) {
            $query->orderBy($sortColumn, $dir);
        } else {
            $query->orderBy('product_types.priority')->orderBy('product_types.name');
        }

        return $query->paginate($perPage);
    }

    public function create(CreateProductTypeDTO $dto): ProductType
    {
        return DB::transaction(function () use ($dto) {

            if ($this->repository->nameExists($dto->tenantId, $dto->name)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.product_type.name_exists'));
            }

            $category = ProductCategory::where('uuid', $dto->productCategoryUuid)
                ->where('tenant_id', $dto->tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $type = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'product_category_id' => $category->id,
                'name' => $dto->name,
                'priority' => $dto->priority,
                'is_active' => $dto->isActive,
            ]);

            event(new ProductTypeCreated(
                productTypeUuid: $type->uuid,
                actorId: Auth::id()
            ));

            return $type;
        });
    }

    public function update(ProductType $type, UpdateProductTypeDTO $dto): ProductType
    {
        $this->assertBelongsToCurrentTenant($type);

        return DB::transaction(function () use ($type, $dto) {

            $original = $type->getOriginal();

            if ($dto->name && $this->repository->nameExists($type->tenant_id, $dto->name, $type->id)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.product_type.name_exists'));
            }

            $data = array_filter([
                'name' => $dto->name,
                'priority' => $dto->priority,
                'is_active' => $dto->isActive,
            ], fn($v) => !is_null($v));

            if ($dto->productCategoryUuid) {
                $category = ProductCategory::where('uuid', $dto->productCategoryUuid)
                    ->where('tenant_id', $type->tenant_id)
                    ->whereNull('deleted_at')
                    ->firstOrFail();

                $data['product_category_id'] = $category->id;
            }

            if (!empty($data)) {
                $type = $this->repository->update($type, $data);

                $changes = array_diff_assoc($type->getAttributes(), $original);

                event(new ProductTypeUpdated(
                    productTypeUuid: $type->uuid,
                    actorId: Auth::id(),
                    changes: array_keys($changes)
                ));
            }

            return $type;
        });
    }

    public function delete(ProductType $type): void
    {
        $this->assertBelongsToCurrentTenant($type);

        DB::transaction(function () use ($type) {
            $this->repository->delete($type);

            event(new ProductTypeDeleted(
                productTypeUuid: $type->uuid,
                actorId: Auth::id()
            ));
        });
    }

    /**
     * Route-model-binding resolve só por uuid, sem escopo de tenant — sem
     * esta checagem, um usuário com permissão poderia mutar registro de
     * outro tenant só sabendo o uuid (IDOR).
     */
    private function assertBelongsToCurrentTenant(ProductType $type): void
    {
        if ((int) $type->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
