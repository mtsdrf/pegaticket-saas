<?php

namespace App\Http\Controllers\Storefront;

use App\Exceptions\CouponUsageLimitReachedException;
use App\Exceptions\InvalidCouponException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StorefrontValidateCouponRequest;
use App\Http\Resources\Event\EventResource;
use App\Http\Resources\Storefront\StorefrontTenantResource;
use App\Models\Event\EventProduct;
use App\Models\Event\TicketType;
use App\Services\APIResponse;
use App\Services\Storefront\CouponService;
use App\Services\Storefront\OrderRatingService;
use App\Services\Storefront\StorefrontCatalogService;
use App\Services\Storefront\StorefrontCheckoutService;
use App\Services\Storefront\StoreBusinessHoursService;
use App\Services\Storefront\StoreDeliveryFeeService;
use App\Services\Tenant\TenantSettingsService;
use Illuminate\Http\Request;

/**
 * Catálogo público migrado de comércio/delivery (Product) para o domínio
 * de ingressos (Event) — roadmap PegaTicket seção 2.4/4A, 2026-07-31.
 * Ver SIMPLIFICAÇÃO DOCUMENTADA em StorefrontCatalogService.
 */
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
                (bool) $settings->storefront_enabled,
                $settings->catalog_layout ?? 'list',
                (bool) ($settings->allow_delivery ?? true),
            ),
            __('messages.storefront.tenant_shown')
        );
    }

    /**
     * Lista de eventos publicados/públicos do catálogo (substitui products()
     * do domínio de comércio).
     */
    public function events(string $slug, Request $request)
    {
        $tenant = $this->service->findTenantBySlug($slug);
        $settings = $this->tenantSettingsService->getForTenant($tenant->id);

        if (!$settings->storefront_enabled) {
            abort(404);
        }

        $filters = $request->only(['name', 'event_category_uuid', 'type']);

        $list = $this->service->paginateEvents(
            $tenant->id,
            $filters,
            (int) $request->get('per_page', 15),
            portal_customer()?->id
        );

        return APIResponse::success(
            EventResource::collection($list),
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
     * Detalhe público de um evento, com ticket_types/event_products
     * aninhados (NOVO — não existia equivalente no catálogo de comércio).
     */
    public function event(string $slug, string $eventSlug)
    {
        $tenant = $this->service->findTenantBySlug($slug);
        $settings = $this->tenantSettingsService->getForTenant($tenant->id);

        if (!$settings->storefront_enabled) {
            abort(404);
        }

        $event = $this->service->findPublicEvent($tenant->id, $eventSlug);

        return APIResponse::success(
            new EventResource($event),
            __('messages.event.show')
        );
    }

    /**
     * Categorias com evento publicado (vitrine) — mesmo espírito de
     * events(): 100% público, sem jwt/tenant/perm.
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
     * Consulta prévia de taxa de entrega — SIMPLIFICAÇÃO DOCUMENTADA:
     * mantido por não ter sido removido explicitamente no roadmap, mas taxa
     * de entrega física não faz sentido pra domínio de ingresso digital;
     * candidato a remoção quando o checkout novo (roadmap seção 2.8/5.10)
     * for desenhado.
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
     * Prévia pública de cupom — subtotal calculado a partir de
     * ticket_type_uuid/event_product_uuid (StorefrontCheckoutService::
     * resolveEffectiveUnitPrice()), exatamente um por item.
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
            $sellable = !empty($item['ticket_type_uuid'])
                ? TicketType::where('uuid', $item['ticket_type_uuid'])->where('tenant_id', $tenant->id)->whereNull('deleted_at')->firstOrFail()
                : EventProduct::where('uuid', $item['event_product_uuid'])->where('tenant_id', $tenant->id)->whereNull('deleted_at')->firstOrFail();

            $unitPriceCents = (int) round(
                $this->checkoutService->resolveEffectiveUnitPrice($sellable, (float) $item['quantity']) * 100
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
