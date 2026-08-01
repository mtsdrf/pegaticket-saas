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
use App\Services\Storefront\SaleRatingService;
use App\Services\Storefront\StorefrontCatalogService;
use App\Services\Storefront\StorefrontCheckoutService;
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
        private TenantSettingsService $tenantSettingsService,
        private StorefrontCheckoutService $checkoutService,
        private CouponService $couponService,
        private SaleRatingService $ratingService,
    ) {
    }

    public function show(string $slug)
    {
        $tenant = $this->service->findTenantBySlug($slug);

        $settings = $this->tenantSettingsService->getForTenant($tenant->id);
        $ratingSummary = $this->ratingService->tenantSummary($tenant->id);

        return APIResponse::success(
            new StorefrontTenantResource(
                $tenant,
                $settings->estimated_preparation_minutes,
                $ratingSummary['average_rating'],
                $ratingSummary['ratings_count'],
                $settings->accepted_payment_methods ?? [],
                (bool) $settings->allow_store_pickup,
                (bool) $settings->storefront_enabled,
                $settings->catalog_layout ?? 'list',
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
