<?php

namespace App\Http\Controllers\Storefront;

use App\Exceptions\CouponUsageLimitReachedException;
use App\Exceptions\InvalidCouponException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StorefrontValidateCouponRequest;
use App\Http\Resources\Storefront\StorefrontProductResource;
use App\Http\Resources\Storefront\StorefrontTenantResource;
use App\Models\Product\Product;
use App\Services\APIResponse;
use App\Services\Storefront\CouponService;
use App\Services\Storefront\OrderRatingService;
use App\Services\Storefront\StorefrontCatalogService;
use App\Services\Storefront\StorefrontCheckoutService;
use App\Services\Storefront\StoreBusinessHoursService;
use App\Services\Storefront\StoreDeliveryFeeService;
use App\Services\Balcao\TableReservationService;
use App\Services\Tenant\TenantSettingsService;
use Illuminate\Http\Request;

class StorefrontController extends Controller
{
    public function __construct(
        private StorefrontCatalogService $service,
        private StoreDeliveryFeeService $deliveryFeeService,
        private StoreBusinessHoursService $businessHoursService,
        private TenantSettingsService $tenantSettingsService,
        private StorefrontCheckoutService $checkoutService,
        private CouponService $couponService,
        private OrderRatingService $ratingService,
        private TableReservationService $tableReservationService,
    ) {
    }

    public function show(string $slug)
    {
        $tenant = $this->service->findTenantBySlug($slug);
        $tenant->load(['endereco.cidade', 'endereco.bairro']);

        $businessHours = $this->businessHoursService->getForTenant($tenant->id);
        $settings = $this->tenantSettingsService->getForTenant($tenant->id);
        $ratingSummary = $this->ratingService->tenantSummary($tenant->id);

        return APIResponse::success(
            new StorefrontTenantResource(
                $tenant,
                $businessHours,
                $settings->estimated_preparation_minutes,
                $ratingSummary['average_rating'],
                $ratingSummary['ratings_count'],
                $settings->accepted_payment_methods ?? [],
                (bool) $settings->allow_store_pickup,
                $this->tableReservationService->publicReservationsEnabled($tenant->id),
                (bool) $settings->storefront_enabled,
                $settings->catalog_layout ?? 'list',
                (bool) ($settings->allow_delivery ?? true),
            ),
            __('messages.storefront.tenant_shown')
        );
    }

    public function products(string $slug, Request $request)
    {
        $tenant = $this->service->findTenantBySlug($slug);
        $settings = $this->tenantSettingsService->getForTenant($tenant->id);

        if (!$settings->storefront_enabled) {
            abort(404);
        }

        $filters = $request->only(['name', 'product_type_uuid', 'product_category_uuid', 'on_promotion', 'sort']);

        $list = $this->service->paginateProducts(
            $tenant->id,
            $filters,
            (int) $request->get('per_page', 15),
            portal_customer()?->id
        );

        return APIResponse::success(
            StorefrontProductResource::collection($list),
            __('messages.storefront.products_listed'),
            200,
            [
                'pagination' => [
                    'current_page' => $list->currentPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'last_page' => $list->lastPage(),
                ]
            ]
        );
    }

    /**
     * Categorias com produto disponível (vitrine estilo iFood) — mesmo
     * espírito de products(): 100% público, sem jwt/tenant/perm.
     */
    public function categories(string $slug)
    {
        $tenant = $this->service->findTenantBySlug($slug);
        $settings = $this->tenantSettingsService->getForTenant($tenant->id);

        if (!$settings->storefront_enabled) {
            abort(404);
        }

        $categories = $this->service->listAvailableCategories($tenant->id)
            ->map(fn($category) => ['uuid' => $category->uuid, 'name' => $category->name])
            ->values();

        return APIResponse::success($categories, __('messages.storefront.categories_listed'));
    }

    /**
     * Consulta prévia de taxa de entrega (roadmap Delivery, Fase 2) — o
     * frontend chama ao escolher o bairro no checkout, antes de confirmar
     * o pedido, mesmo espírito de products(): 100% público, sem
     * jwt/tenant/perm. 404 quando o bairro não tem taxa cadastrada pro
     * tenant (mesma regra do guard 3 do checkout: nunca frete
     * grátis/padrão implícito).
     */
    public function deliveryFee(string $slug, string $bairroUuid)
    {
        $tenant = $this->service->findTenantBySlug($slug);
        $settings = $this->tenantSettingsService->getForTenant($tenant->id);

        if (!$settings->storefront_enabled) {
            abort(404);
        }

        $fee = $this->deliveryFeeService->findFee($tenant->id, $bairroUuid);

        if ($fee === null) {
            return APIResponse::error(
                __('messages.storefront.delivery_area_not_served'),
                404,
                'DELIVERY_AREA_NOT_SERVED'
            );
        }

        return APIResponse::success(['fee' => $fee], __('messages.storefront.delivery_fee_shown'));
    }

    /**
     * Prévia pública de cupom (roadmap Delivery, Fase 3) — o frontend chama
     * ao digitar o código no checkout, ANTES do OTP/identificação do
     * cliente final. Subtotal calculado com
     * StorefrontCheckoutService::resolveEffectiveUnitPrice() SEM Client
     * (promoção/preço base, nunca desconto de categoria — cliente
     * desconhecido nesta etapa). Usa CouponService::validatePreview(), que
     * roda todos os checks de validateForCheckout() MENOS o limite por
     * cliente (só garantido de fato no submit final do checkout, mesmo
     * padrão já documentado pra taxa de entrega na Fase 2).
     *
     * type=free_shipping: sem bairro escolhido aqui (body só tem
     * code+items), não há taxa de entrega resolvida ainda — discount_amount
     * retorna 0 nesse caso (simplificação documentada), o frontend exibe
     * "frete grátis" pelo campo `type`; o desconto real é calculado no
     * guard 4 do checkout final.
     */
    public function validateCoupon(string $slug, StorefrontValidateCouponRequest $request)
    {
        $tenant = $this->service->findTenantBySlug($slug);
        $settings = $this->tenantSettingsService->getForTenant($tenant->id);

        if (!$settings->storefront_enabled) {
            abort(404);
        }

        $data = $request->validated();

        $subtotalCents = 0;

        foreach ($data['items'] as $item) {
            $product = Product::where('uuid', $item['product_uuid'])
                ->where('tenant_id', $tenant->id)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $unitPriceCents = (int) round(
                $this->checkoutService->resolveEffectiveUnitPrice($product, null, (float) $item['quantity']) * 100
            );
            $subtotalCents += (int) round($unitPriceCents * (float) $item['quantity']);
        }

        try {
            $coupon = $this->couponService->validatePreview($tenant->id, $data['code'], $subtotalCents);
        } catch (InvalidCouponException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_COUPON');
        } catch (CouponUsageLimitReachedException $e) {
            return APIResponse::error($e->getMessage(), 422, 'COUPON_USAGE_LIMIT_REACHED');
        }

        $discountAmountCents = $this->couponService->calculateDiscountCents($coupon, $subtotalCents);

        return APIResponse::success([
            'type' => $coupon->type,
            'value' => $coupon->value !== null ? (float) $coupon->value : null,
            'discount_amount' => $discountAmountCents / 100,
        ], __('messages.coupon.validated'));
    }
}
