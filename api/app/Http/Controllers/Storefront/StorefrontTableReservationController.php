<?php

namespace App\Http\Controllers\Storefront;

use App\Exceptions\Balcao\TableReservationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StorefrontTableReservationRequest;
use App\Http\Resources\Balcao\TableReservationResource;
use App\Http\Resources\Storefront\PublicReservationTenantResource;
use App\Models\Balcao\TableReservation;
use App\Services\APIResponse;
use App\Services\Balcao\TableReservationService;
use App\Services\Permission\PermissionService;
use App\Services\Storefront\StorefrontCatalogService;
use App\Services\Tenant\TenantSettingsService;

class StorefrontTableReservationController extends Controller
{
    public function __construct(
        private StorefrontCatalogService $catalogService,
        private TableReservationService $reservationService,
        private PermissionService $permissionService,
        private TenantSettingsService $tenantSettingsService,
    ) {
    }

    public function show(string $slug)
    {
        $tenant = $this->catalogService->findPublicTenantBySlug($slug);
        $settings = $this->tenantSettingsService->getForTenant($tenant->id);

        return APIResponse::success(
            new PublicReservationTenantResource(
                $tenant,
                $this->reservationService->publicReservationsEnabled($tenant->id),
                $this->permissionService->tenantPlanAllowsFunctionality($tenant->id, 'storefront')
                    && (bool) $settings->storefront_enabled,
            ),
            __('messages.table_reservation.public_available')
        );
    }

    public function store(string $slug, StorefrontTableReservationRequest $request)
    {
        $tenant = $this->catalogService->findTenantBySlug($slug);

        return $this->storeForTenant($tenant->id, $request);
    }

    public function storePublic(string $slug, StorefrontTableReservationRequest $request)
    {
        $tenant = $this->catalogService->findPublicTenantBySlug($slug);

        return $this->storeForTenant($tenant->id, $request);
    }

    private function storeForTenant(int $tenantId, StorefrontTableReservationRequest $request)
    {
        if (!$this->reservationService->publicReservationsEnabled($tenantId)) {
            return APIResponse::error(
                __('messages.table_reservation.public_unavailable'),
                404,
                'TABLE_RESERVATION_UNAVAILABLE'
            );
        }

        try {
            $reservation = $this->reservationService->create(
                $tenantId,
                $request->validated(),
                TableReservation::SOURCE_ONLINE
            );
        } catch (TableReservationException $e) {
            return APIResponse::error($e->getMessage(), 422, 'TABLE_RESERVATION_ERROR');
        }

        return APIResponse::success(
            new TableReservationResource($reservation),
            __('messages.table_reservation.public_created'),
            201
        );
    }
}
