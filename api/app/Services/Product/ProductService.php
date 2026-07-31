<?php

namespace App\Services\Product;

use App\DTOs\Product\CreateProductDTO;
use App\DTOs\Product\ProductOptionGroupInput;
use App\DTOs\Product\ProductOptionInput;
use App\DTOs\Product\UpdateProductDTO;
use App\Events\Product\ProductCreated;
use App\Events\Product\ProductUpdated;
use App\Events\Product\ProductDeleted;
use App\Models\Product\Product;
use App\Models\Product\ProductOption;
use App\Models\Product\ProductOptionGroup;
use App\Models\Product\ProductType;
use App\Models\Tenant\Tenant;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\Media\MediaStorageService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductService
{
    public const EAGER_RELATIONS = ['productType.productCategory'];

    public const DETAIL_RELATIONS = ['productType.productCategory', 'optionGroups.options'];

    /**
     * Colunas de `products` exceto `image_data` (LONGBLOB) — listagem/grid
     * não pode puxar o blob pra cada linha da página (custo de I/O e memória
     * multiplicado pelo per_page). ProductResource só precisa de
     * `image_mime` (string pequena) pra saber se existe imagem e montar a
     * URL; os bytes reais só são lidos pela rota dedicada de servir a
     * imagem. Cacheado estaticamente (1 DESCRIBE por processo, não por
     * chamada). Público: reaproveitado por StorefrontCatalogService::
     * paginateProducts() (catálogo público, mesmo risco de N linhas x BLOB).
     */
    public static function listColumns(): array
    {
        static $columns;

        return $columns ??= array_map(
            fn($c) => "products.$c",
            array_diff(Schema::getColumnListing('products'), ['image_data'])
        );
    }

    public function __construct(
        private ProductRepositoryInterface $repository,
        private MediaStorageService $mediaStorage
    ) {
    }

    public function find(Product $product): Product
    {
        $this->assertBelongsToCurrentTenant($product);

        return $product;
    }

    /**
     * Whitelist de sort_by aceito pelo grid — product_type_name e
     * product_category_name exigem leftJoin (belongsTo 1:1, não duplica
     * linhas). Join de product_categories depende do join de product_types
     * já estar presente (cadeia products -> product_types -> product_categories).
     */
    private const SORTABLE = [
        'name' => 'products.name',
        'product_type_name' => 'product_types.name',
        'product_category_name' => 'product_categories.name',
        'price' => 'products.price',
        'is_available' => 'products.is_available',
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
        $needsTypeJoin = $needsCategoryJoin
            || $sortColumn === 'product_types.name'
            || !empty($filters['product_type_name']);

        $query = Product::query()
            ->select(self::listColumns())
            ->where('products.tenant_id', $tenantId)
            ->whereNull('products.deleted_at')
            ->with(self::EAGER_RELATIONS)
            ->withSum('stockBalances', 'quantity_on_hand');

        if ($needsTypeJoin) {
            $query->leftJoin('product_types', 'product_types.id', '=', 'products.product_type_id');
        }

        if ($needsCategoryJoin) {
            $query->leftJoin('product_categories', 'product_categories.id', '=', 'product_types.product_category_id');
        }

        if (!empty($filters['name'])) {
            $query->where('products.name', 'like', '%' . $filters['name'] . '%');
        }

        // Código de barras: match exato (o leitor do PDV bipa o código
        // completo e espera casar exatamente um produto), não LIKE.
        if (!empty($filters['barcode'])) {
            $query->where('products.barcode', $filters['barcode']);
        }

        if (array_key_exists('is_available', $filters) && $filters['is_available'] !== null) {
            $query->where('products.is_available', filter_var($filters['is_available'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['product_type_uuid'])) {
            $query->whereHas('productType', fn($q) => $q->where('uuid', $filters['product_type_uuid']));
        }

        if (!empty($filters['product_category_uuid'])) {
            $query->whereHas('productType.productCategory', fn($q) => $q->where('uuid', $filters['product_category_uuid']));
        }

        if (!empty($filters['product_type_name'])) {
            $query->where('product_types.name', 'like', '%' . $filters['product_type_name'] . '%');
        }

        if (!empty($filters['product_category_name'])) {
            $query->where('product_categories.name', 'like', '%' . $filters['product_category_name'] . '%');
        }

        if (array_key_exists('price_min', $filters) && $filters['price_min'] !== null && $filters['price_min'] !== '') {
            $query->where('products.price', '>=', $filters['price_min']);
        }

        if (array_key_exists('price_max', $filters) && $filters['price_max'] !== null && $filters['price_max'] !== '') {
            $query->where('products.price', '<=', $filters['price_max']);
        }

        // Busca global (campo único acima do grid) — OR entre as colunas
        // buscáveis, agrupado num closure próprio para não virar AND com os
        // filtros por coluna acima. Usa whereHas (subquery própria), não
        // depende do leftJoin condicional usado pelo sort_by.
        if (!empty($filters['q'])) {
            $term = $filters['q'];
            $query->where(function ($sub) use ($term) {
                $sub->where('products.name', 'like', '%' . $term . '%')
                    ->orWhereHas('productType', fn($r) => $r->where('name', 'like', '%' . $term . '%'))
                    ->orWhereHas('productType.productCategory', fn($r) => $r->where('name', 'like', '%' . $term . '%'));
            });
        }

        $dir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        if ($sortColumn) {
            $query->orderBy($sortColumn, $dir);
        } else {
            $query->orderBy('products.name');
        }

        return $query->paginate($perPage);
    }

    /**
     * Catálogo completo (sem paginação) pro PDF "para o cliente" — mesmos
     * filtros de paginate(), mas usa whereHas em vez do leftJoin condicional
     * porque aqui não há sort_by nem select de coluna a proteger, só filtro.
     */
    public function forPdf(int $tenantId, array $filters = []): \Illuminate\Support\Collection
    {
        $query = Product::query()
            ->where('products.tenant_id', $tenantId)
            ->whereNull('products.deleted_at')
            ->with(self::EAGER_RELATIONS);

        if (!empty($filters['name'])) {
            $query->where('products.name', 'like', '%' . $filters['name'] . '%');
        }

        // Código de barras: match exato (o leitor do PDV bipa o código
        // completo e espera casar exatamente um produto), não LIKE.
        if (!empty($filters['barcode'])) {
            $query->where('products.barcode', $filters['barcode']);
        }

        if (array_key_exists('is_available', $filters) && $filters['is_available'] !== null) {
            $query->where('products.is_available', filter_var($filters['is_available'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['product_type_uuid'])) {
            $query->whereHas('productType', fn($q) => $q->where('uuid', $filters['product_type_uuid']));
        }

        if (!empty($filters['product_category_uuid'])) {
            $query->whereHas('productType.productCategory', fn($q) => $q->where('uuid', $filters['product_category_uuid']));
        }

        if (!empty($filters['product_type_name'])) {
            $query->whereHas('productType', fn($q) => $q->where('name', 'like', '%' . $filters['product_type_name'] . '%'));
        }

        if (!empty($filters['product_category_name'])) {
            $query->whereHas('productType.productCategory', fn($q) => $q->where('name', 'like', '%' . $filters['product_category_name'] . '%'));
        }

        if (array_key_exists('price_min', $filters) && $filters['price_min'] !== null && $filters['price_min'] !== '') {
            $query->where('products.price', '>=', $filters['price_min']);
        }

        if (array_key_exists('price_max', $filters) && $filters['price_max'] !== null && $filters['price_max'] !== '') {
            $query->where('products.price', '<=', $filters['price_max']);
        }

        if (!empty($filters['q'])) {
            $term = $filters['q'];
            $query->where(function ($sub) use ($term) {
                $sub->where('products.name', 'like', '%' . $term . '%')
                    ->orWhereHas('productType', fn($r) => $r->where('name', 'like', '%' . $term . '%'))
                    ->orWhereHas('productType.productCategory', fn($r) => $r->where('name', 'like', '%' . $term . '%'));
            });
        }

        return $query->orderBy('products.name')->get();
    }

    public function create(CreateProductDTO $dto): Product
    {
        $newMedia = null;

        if ($dto->image) {
            $newMedia = $this->mediaStorage->storePublicImage($dto->image, $this->resolveProductMediaDirectory($dto->tenantId), 'product');
        }

        try {
            return DB::transaction(function () use ($dto, $newMedia) {

                $type = ProductType::where('uuid', $dto->productTypeUuid)
                    ->where('tenant_id', $dto->tenantId)
                    ->whereNull('deleted_at')
                    ->firstOrFail();

                $product = $this->repository->create([
                    'tenant_id' => $dto->tenantId,
                    'product_type_id' => $type->id,
                    'name' => $dto->name,
                    'price' => $dto->price,
                    'description' => $dto->description,
                    'image_path' => $newMedia['path'] ?? null,
                    'image_data' => null,
                    'image_mime' => $newMedia['mime'] ?? null,
                    'image_updated_at' => $newMedia['updated_at'] ?? null,
                    'is_available' => $dto->isAvailable,
                    'surcharge_rate' => $dto->surchargeRate,
                    'sku' => $dto->sku,
                    'barcode' => $dto->barcode,
                    'brand' => $dto->brand,
                    'ncm' => $dto->ncm,
                    'cest' => $dto->cest,
                    'origin' => $dto->origin,
                    'default_cfop' => $dto->defaultCfop,
                    'csosn_cst' => $dto->csosnCst,
                    'unit' => $dto->unit,
                    'is_lot_controlled' => $dto->isLotControlled,
                    'is_expiry_controlled' => $dto->isExpiryControlled,
                    'is_serial_controlled' => $dto->isSerialControlled,
                    'min_stock' => $dto->minStock,
                    'max_stock' => $dto->maxStock,
                    'reorder_point' => $dto->reorderPoint,
                    'reorder_qty' => $dto->reorderQty,
                    'last_purchase_cost' => $dto->lastPurchaseCost,
                ]);

                $this->syncOptionGroups($product, $dto->optionGroups);

                event(new ProductCreated(
                    productUuid: $product->uuid,
                    actorId: Auth::id()
                ));

                return $product;
            });
        } catch (\Throwable $e) {
            if ($newMedia) {
                $this->mediaStorage->deletePublicMedia($newMedia['path'], 'product');
            }

            throw $e;
        }
    }

    public function update(Product $product, UpdateProductDTO $dto): Product
    {
        $this->assertBelongsToCurrentTenant($product);

        $newMedia = null;
        $oldPath = $product->image_path;

        if ($dto->image) {
            $newMedia = $this->mediaStorage->storePublicImage($dto->image, $this->resolveProductMediaDirectory($product->tenant_id), 'product');
        }

        try {
            return DB::transaction(function () use ($product, $dto, $newMedia, $oldPath) {

                $original = $product->getOriginal();

                $data = array_filter([
                    'name' => $dto->name,
                    'price' => $dto->price,
                    'description' => $dto->description,
                    'is_available' => $dto->isAvailable,
                    'surcharge_rate' => $dto->surchargeRate,
                    'sku' => $dto->sku,
                    'barcode' => $dto->barcode,
                    'brand' => $dto->brand,
                    'ncm' => $dto->ncm,
                    'cest' => $dto->cest,
                    'origin' => $dto->origin,
                    'default_cfop' => $dto->defaultCfop,
                    'csosn_cst' => $dto->csosnCst,
                    'unit' => $dto->unit,
                    'is_lot_controlled' => $dto->isLotControlled,
                    'is_expiry_controlled' => $dto->isExpiryControlled,
                    'is_serial_controlled' => $dto->isSerialControlled,
                    'min_stock' => $dto->minStock,
                    'max_stock' => $dto->maxStock,
                    'reorder_point' => $dto->reorderPoint,
                    'reorder_qty' => $dto->reorderQty,
                    'last_purchase_cost' => $dto->lastPurchaseCost,
                ], fn($v) => !is_null($v));

                if ($dto->productTypeUuid) {
                    $type = ProductType::where('uuid', $dto->productTypeUuid)
                        ->where('tenant_id', $product->tenant_id)
                        ->whereNull('deleted_at')
                        ->firstOrFail();

                    $data['product_type_id'] = $type->id;
                }

                if ($newMedia) {
                    $data['image_path'] = $newMedia['path'];
                    $data['image_data'] = null;
                    $data['image_mime'] = $newMedia['mime'];
                    $data['image_updated_at'] = $newMedia['updated_at'];
                }

                if (!empty($data)) {
                    $product = $this->repository->update($product, $data);
                }

                if ($dto->optionGroups !== null) {
                    $this->syncOptionGroups($product, $dto->optionGroups);
                }

                $product->refresh();

                if ($newMedia && $oldPath && $oldPath !== $newMedia['path']) {
                    DB::afterCommit(fn() => $this->mediaStorage->deletePublicMedia($oldPath, 'product'));
                }

                $changes = array_diff_assoc($product->getAttributes(), $original);

                if (!empty($changes)) {
                    event(new ProductUpdated(
                        productUuid: $product->uuid,
                        actorId: Auth::id(),
                        changes: array_keys($changes)
                    ));
                }

                return $product;
            });
        } catch (\Throwable $e) {
            if ($newMedia) {
                $this->mediaStorage->deletePublicMedia($newMedia['path'], 'product');
            }

            throw $e;
        }
    }

    /**
     * "Bloquear produto" no PWA (roadmap A4, item 16) — atalho para
     * update() sem exigir o payload inteiro do produto. $isAvailable=null
     * inverte o valor atual (toggle); informado, seta explicitamente.
     * Reaproveita update() por inteiro (auditoria via ProductUpdated já
     * disparada lá, mesmo guard de posse do tenant).
     */
    public function toggleAvailability(Product $product, ?bool $isAvailable = null): Product
    {
        $target = $isAvailable ?? !$product->is_available;

        $dto = UpdateProductDTO::fromArray(['is_available' => $target]);

        return $this->update($product, $dto);
    }

    public function delete(Product $product): void
    {
        $this->assertBelongsToCurrentTenant($product);

        DB::transaction(function () use ($product) {
            $this->repository->delete($product);

            event(new ProductDeleted(
                productUuid: $product->uuid,
                actorId: Auth::id()
            ));
        });
    }

    /**
     * Route-model-binding resolve só por uuid, sem escopo de tenant — sem
     * esta checagem, um usuário com permissão poderia mutar registro de
     * outro tenant só sabendo o uuid (IDOR).
     */
    private function assertBelongsToCurrentTenant(Product $product): void
    {
        if ((int) $product->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }

    private function resolveProductMediaDirectory(int $tenantId): string
    {
        return (string) Tenant::query()
            ->whereKey($tenantId)
            ->value('uuid');
    }

    /**
     * @param array<int, ProductOptionGroupInput> $groups
     */
    private function syncOptionGroups(Product $product, array $groups): void
    {
        $product->loadMissing('optionGroups.options');

        $existingGroups = $product->optionGroups->keyBy('uuid');
        $keptGroupIds = [];

        foreach ($groups as $groupInput) {
            $group = $groupInput->uuid ? $existingGroups->get($groupInput->uuid) : null;

            if ($group) {
                $group->fill([
                    'name' => $groupInput->name,
                    'description' => $groupInput->description,
                    'kind' => $groupInput->kind,
                    'min_select' => $groupInput->minSelect,
                    'max_select' => $groupInput->maxSelect,
                    'sort_order' => $groupInput->sortOrder,
                    'is_active' => $groupInput->isActive,
                ])->save();
            } else {
                $group = ProductOptionGroup::create([
                    'tenant_id' => $product->tenant_id,
                    'product_id' => $product->id,
                    'name' => $groupInput->name,
                    'description' => $groupInput->description,
                    'kind' => $groupInput->kind,
                    'min_select' => $groupInput->minSelect,
                    'max_select' => $groupInput->maxSelect,
                    'sort_order' => $groupInput->sortOrder,
                    'is_active' => $groupInput->isActive,
                ]);
            }

            $keptGroupIds[] = $group->id;
            $this->syncOptions($group, $groupInput->options, $product->tenant_id);
        }

        $groupsToDelete = ProductOptionGroup::query()
            ->where('product_id', $product->id)
            ->when($keptGroupIds !== [], fn ($query) => $query->whereNotIn('id', $keptGroupIds))
            ->get();

        foreach ($groupsToDelete as $groupToDelete) {
            $groupToDelete->options()->delete();
            $groupToDelete->delete();
        }
    }

    /**
     * @param array<int, ProductOptionInput> $options
     */
    private function syncOptions(ProductOptionGroup $group, array $options, int $tenantId): void
    {
        $group->loadMissing('options');

        $existingOptions = $group->options->keyBy('uuid');
        $keptOptionIds = [];

        foreach ($options as $optionInput) {
            $option = $optionInput->uuid ? $existingOptions->get($optionInput->uuid) : null;

            if ($option) {
                $option->fill([
                    'name' => $optionInput->name,
                    'description' => $optionInput->description,
                    'price' => $optionInput->price,
                    'sort_order' => $optionInput->sortOrder,
                    'is_available' => $optionInput->isAvailable,
                ])->save();
            } else {
                $option = ProductOption::create([
                    'tenant_id' => $tenantId,
                    'product_option_group_id' => $group->id,
                    'name' => $optionInput->name,
                    'description' => $optionInput->description,
                    'price' => $optionInput->price,
                    'sort_order' => $optionInput->sortOrder,
                    'is_available' => $optionInput->isAvailable,
                ]);
            }

            $keptOptionIds[] = $option->id;
        }

        ProductOption::query()
            ->where('product_option_group_id', $group->id)
            ->when($keptOptionIds !== [], fn ($query) => $query->whereNotIn('id', $keptOptionIds))
            ->delete();
    }
}
