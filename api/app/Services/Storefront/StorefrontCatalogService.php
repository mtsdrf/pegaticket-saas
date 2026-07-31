<?php

namespace App\Services\Storefront;

use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Storefront\ProductFavorite;
use App\Models\Tenant\Tenant;
use App\Services\Permission\PermissionService;
use App\Services\Product\ProductService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo público da loja (roadmap Delivery, Fase 1). Sem tenant/perm —
 * o "escopo" aqui é resolvido por slug, não por JWT/membership. Toda
 * resolução de tenant passa por findTenantBySlug(), que já embute o gate
 * de plano: 404 tanto para slug inexistente quanto para tenant sem a
 * functionality 'storefront' no plano, nunca revelando qual dos dois caso é
 * (mesmo espírito de request-otp do Portal, que nunca revela se o e-mail já
 * tinha conta).
 */
class StorefrontCatalogService
{
    // Selos do card (gap-analysis de catálogo/cardápio, item 3) — critérios
    // deliberadamente simples/baratos, sem tabela nova:
    // 'new': produtos cadastrados nos últimos N dias.
    // 'best_selling': entre os mais vendidos (90 dias), mesma janela/base
    //   de filtro do sort=best_selling (ver applySort()) e de
    //   AnalyticsService::stockRuptures().
    // 'low_stock': saldo disponível baixo (>0 e <= limiar), mesma fórmula
    //   de disponibilidade (on_hand - reserved - blocked) usada em
    //   AnalyticsService::stockRuptures().
    private const NEW_PRODUCT_DAYS = 14;
    private const BEST_SELLING_BADGE_LIMIT = 10;
    private const LOW_STOCK_THRESHOLD = 5;

    public function __construct(
        private PermissionService $permissionService,
        private ProductPromotionService $productPromotionService,
    ) {
    }

    public function findTenantBySlug(string $slug): Tenant
    {
        $tenant = $this->findPublicTenantBySlug($slug);

        if (!$this->permissionService->tenantPlanAllowsFunctionality($tenant->id, 'storefront')) {
            abort(404);
        }

        return $tenant;
    }

    public function findPublicTenantBySlug(string $slug): Tenant
    {
        return Tenant::where('slug', $slug)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    /**
     * Reaproveita o shape de paginação de ProductService::paginate(), mas
     * força is_available=true incondicionalmente (nunca aceita filtro
     * externo que desligue isso) e nunca soma estoque (withSum) nem expõe
     * custo — catálogo público não mostra número de estoque nem custo.
     *
     * `on_promotion=1` (roadmap Delivery, Fase 3): filtra só produtos com
     * promoção ATIVA e dentro da janela de datas — sustenta uma seção "Em
     * promoção" na home sem endpoint novo. Promoções ativas da página
     * inteira são carregadas de UMA vez (keyed por product_id, mesmo padrão
     * de ProductPromotionRepository::activePromotionsForProducts()) e
     * atribuídas como atributo dinâmico `promo_price` em cada Product antes
     * de retornar — StorefrontProductResource só lê esse atributo, sem N+1.
     */
    public function paginateProducts(
        int $tenantId,
        array $filters = [],
        int $perPage = 15,
        ?int $finalCustomerId = null
    ): LengthAwarePaginator
    {
        $query = Product::query()
            ->select(ProductService::listColumns())
            ->where('products.tenant_id', $tenantId)
            ->whereNull('products.deleted_at')
            ->where('products.is_available', true)
            ->with([
                'productType.productCategory',
                'optionGroups' => fn($groupQuery) => $groupQuery
                    ->where('is_active', true)
                    ->with(['options' => fn($optionQuery) => $optionQuery->where('is_available', true)]),
            ]);

        if (!empty($filters['name'])) {
            $query->where('products.name', 'like', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['product_type_uuid'])) {
            $query->whereHas('productType', fn($q) => $q->where('uuid', $filters['product_type_uuid']));
        }

        if (!empty($filters['product_category_uuid'])) {
            $query->whereHas('productType.productCategory', fn($q) => $q->where('uuid', $filters['product_category_uuid']));
        }

        if (filter_var($filters['on_promotion'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $now = now();

            $query->whereExists(function ($q) use ($tenantId, $now) {
                $q->select(DB::raw(1))
                    ->from('product_promotions')
                    ->whereColumn('product_promotions.product_id', 'products.id')
                    ->where('product_promotions.tenant_id', $tenantId)
                    ->where('product_promotions.is_active', true)
                    ->whereNull('product_promotions.deleted_at')
                    ->where(fn($sub) => $sub->whereNull('product_promotions.starts_at')->orWhere('product_promotions.starts_at', '<=', $now))
                    ->where(fn($sub) => $sub->whereNull('product_promotions.expires_at')->orWhere('product_promotions.expires_at', '>=', $now));
            });
        }

        $this->applySort($query, $tenantId, $filters['sort'] ?? null);

        $page = $query->paginate($perPage);

        $productIds = $page->getCollection()->pluck('id')->all();
        $promotions = $this->productPromotionService->activePromotionsForProducts($tenantId, $productIds);

        $page->getCollection()->each(function (Product $product) use ($promotions) {
            $promotion = $promotions->get($product->id);
            $product->setAttribute('promo_price', $promotion ? $promotion->effectivePrice((float) $product->price) : null);
        });

        $badgeIds = $this->resolveBadgeIds($tenantId, $productIds);
        $newSince = now()->subDays(self::NEW_PRODUCT_DAYS);

        $page->getCollection()->each(function (Product $product) use ($badgeIds, $newSince) {
            $badges = [];

            if ($product->created_at !== null && $product->created_at->greaterThanOrEqualTo($newSince)) {
                $badges[] = 'new';
            }

            if (in_array($product->id, $badgeIds['best_selling'], true)) {
                $badges[] = 'best_selling';
            }

            if (in_array($product->id, $badgeIds['low_stock'], true)) {
                $badges[] = 'low_stock';
            }

            $product->setAttribute('badges', $badges);
        });

        // is_favorited (roadmap Delivery, Fase 4 — retenção): só calculado
        // quando o catálogo é acessado com portal_customer() populado
        // (customer.jwt.optional); batch único via whereIn, evita N+1.
        if ($finalCustomerId !== null) {
            $favoritedIds = ProductFavorite::where('final_customer_id', $finalCustomerId)
                ->whereIn('product_id', $productIds)
                ->pluck('product_id')
                ->all();

            $page->getCollection()->each(function (Product $product) use ($favoritedIds) {
                $product->setAttribute('is_favorited', in_array($product->id, $favoritedIds, true));
            });
        }

        return $page;
    }

    /**
     * Whitelist de ordenação da vitrine pública — nunca aceita coluna/direção
     * vinda direto do cliente (`orderByRaw` só com SQL fixo nosso, filtro
     * desconhecido cai silenciosamente no default). `price_*` usa o preço
     * EFETIVO (promoção ativa quando houver, senão preço base) via
     * `leftJoinSub`, senão "menor preço" mentiria pro cliente ignorando
     * promoção. `best_selling` = soma de `order_items.quantity` dos últimos
     * 90 dias, mesma base de filtro (tenant, não cancelado, não deletado) de
     * `AnalyticsService::orderItemsQuery()` — não reaproveita aquele método
     * porque é privado e vive no contexto de relatório staff, não catálogo
     * público; mesma regra, sem expor os números ao cliente final.
     */
    private function applySort($query, int $tenantId, ?string $sort): void
    {
        $now = now();

        if ($sort === 'price_asc' || $sort === 'price_desc') {
            $query->leftJoinSub(
                DB::table('product_promotions')
                    ->where('product_promotions.tenant_id', $tenantId)
                    ->where('product_promotions.is_active', true)
                    ->whereNull('product_promotions.deleted_at')
                    ->where(fn($sub) => $sub->whereNull('product_promotions.starts_at')->orWhere('product_promotions.starts_at', '<=', $now))
                    ->where(fn($sub) => $sub->whereNull('product_promotions.expires_at')->orWhere('product_promotions.expires_at', '>=', $now))
                    ->select(
                        'product_promotions.product_id',
                        'product_promotions.promo_price',
                        'product_promotions.discount_type',
                        'product_promotions.discount_percentage'
                    ),
                'sort_promo',
                'sort_promo.product_id',
                '=',
                'products.id'
            );

            $direction = $sort === 'price_asc' ? 'ASC' : 'DESC';
            // Promoção 'percentage' não tem promo_price fixo — o preço
            // efetivo pro sort é calculado aqui em cima de products.price
            // (mesma fórmula de ProductPromotion::effectivePrice()), já
            // disponível na query externa.
            $query->orderByRaw(
                "COALESCE(
                    CASE sort_promo.discount_type
                        WHEN 'percentage' THEN products.price * (1 - (sort_promo.discount_percentage / 100))
                        ELSE sort_promo.promo_price
                    END,
                    products.price
                ) {$direction}"
            );

            return;
        }

        if ($sort === 'best_selling') {
            $query->leftJoinSub(
                DB::table('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->where('orders.tenant_id', $tenantId)
                    ->whereNull('orders.deleted_at')
                    ->whereNull('orders.cancelled_at')
                    ->whereNull('order_items.deleted_at')
                    ->where('orders.created_at', '>=', $now->copy()->subDays(90))
                    ->groupBy('order_items.product_id')
                    ->selectRaw('order_items.product_id, SUM(order_items.quantity) as qty_sold'),
                'sort_sales',
                'sort_sales.product_id',
                '=',
                'products.id'
            );

            $query->orderByRaw('COALESCE(sort_sales.qty_sold, 0) DESC')->orderBy('products.name');

            return;
        }

        $query->orderBy('products.name');
    }

    /**
     * @param array<int, int> $productIds
     * @return array{best_selling: array<int, int>, low_stock: array<int, int>}
     */
    private function resolveBadgeIds(int $tenantId, array $productIds): array
    {
        if ($productIds === []) {
            return ['best_selling' => [], 'low_stock' => []];
        }

        // Top N vendidos do TENANT (não só da página atual) nos últimos 90
        // dias — mesma base de filtro (tenant, não cancelado, não
        // deletado) de AnalyticsService::stockRuptures()/applySort()
        // best_selling, sem expor os números ao cliente final (só o selo).
        $bestSelling = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->whereNull('orders.cancelled_at')
            ->whereNull('order_items.deleted_at')
            ->where('orders.created_at', '>=', now()->subDays(90))
            ->groupBy('order_items.product_id')
            ->orderByRaw('SUM(order_items.quantity) DESC')
            ->limit(self::BEST_SELLING_BADGE_LIMIT)
            ->pluck('order_items.product_id')
            ->all();

        $lowStock = DB::table('stock_balances')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity_on_hand - quantity_reserved - quantity_blocked) as available')
            ->havingRaw('available > 0 AND available <= ?', [self::LOW_STOCK_THRESHOLD])
            ->pluck('product_id')
            ->all();

        return ['best_selling' => $bestSelling, 'low_stock' => $lowStock];
    }

    /**
     * Categorias com pelo menos 1 produto disponível (Delivery — vitrine
     * estilo iFood) — nunca mostrar uma aba de categoria vazia. Ordenado por
     * `priority` (já usado no cadastro de categorias) e depois nome.
     */
    public function listAvailableCategories(int $tenantId): \Illuminate\Support\Collection
    {
        return ProductCategory::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('product_types')
                    ->join('products', 'products.product_type_id', '=', 'product_types.id')
                    ->whereColumn('product_types.product_category_id', 'product_categories.id')
                    ->whereNull('product_types.deleted_at')
                    ->where('products.is_available', true)
                    ->whereNull('products.deleted_at');
            })
            ->orderBy('priority')
            ->orderBy('name')
            ->get(['uuid', 'name']);
    }
}
