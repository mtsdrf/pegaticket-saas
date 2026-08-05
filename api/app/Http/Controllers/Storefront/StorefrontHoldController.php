<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StorefrontAvailabilityRequest;
use App\Http\Requests\Storefront\StorefrontCreateHoldRequest;
use App\Http\Requests\Storefront\StorefrontManageHoldRequest;
use App\Http\Requests\Storefront\StorefrontQueueStatusRequest;
use App\Http\Resources\Storefront\InventoryHoldResource;
use App\Services\APIResponse;
use App\Services\Security\AntiBotGuardService;
use App\Services\Storefront\StorefrontHoldService;
use App\Services\Storefront\VirtualQueueService;

class StorefrontHoldController extends Controller
{
    public function __construct(
        private StorefrontHoldService $service,
        private VirtualQueueService $virtualQueueService,
        private AntiBotGuardService $antiBotGuard,
    ) {}

    public function queueStatus(string $slug, string $eventSlug, StorefrontQueueStatusRequest $request)
    {
        $data = $this->virtualQueueService->enterOrStatus(
            $slug,
            $eventSlug,
            (string) $request->validated('session_token'),
            portal_customer()
        );

        return APIResponse::success($data, __('messages.virtual_queue.status_shown'));
    }

    public function availability(string $slug, string $eventSlug, StorefrontAvailabilityRequest $request)
    {
        $data = $this->service->availability(
            $slug,
            $eventSlug,
            $request->validated('session_uuid')
        );

        return APIResponse::success($data, __('messages.storefront.availability_shown'));
    }

    public function store(string $slug, string $eventSlug, StorefrontCreateHoldRequest $request)
    {
        $this->antiBotGuard->assertHuman(
            $request->validated('website'),
            $request->validated('form_rendered_at'),
            $request->validated('turnstile_token'),
            $request->ip()
        );

        $hold = $this->service->createHold(
            $slug,
            $eventSlug,
            $request->validated(),
            portal_customer()
        );

        return APIResponse::success(
            new InventoryHoldResource($hold),
            __('messages.inventory_hold.created'),
            201
        );
    }

    public function show(string $slug, string $holdUuid, StorefrontManageHoldRequest $request)
    {
        $hold = $this->service->showHold(
            $slug,
            $holdUuid,
            $request->validated('session_token'),
            portal_customer()
        );

        return APIResponse::success(
            new InventoryHoldResource($hold),
            __('messages.inventory_hold.show')
        );
    }

    public function renew(string $slug, string $holdUuid, StorefrontManageHoldRequest $request)
    {
        $hold = $this->service->renewHold(
            $slug,
            $holdUuid,
            $request->validated('session_token'),
            portal_customer()
        );

        return APIResponse::success(
            new InventoryHoldResource($hold),
            __('messages.inventory_hold.renewed')
        );
    }

    public function destroy(string $slug, string $holdUuid, StorefrontManageHoldRequest $request)
    {
        $hold = $this->service->releaseHold(
            $slug,
            $holdUuid,
            $request->validated('session_token'),
            portal_customer()
        );

        return APIResponse::success(
            new InventoryHoldResource($hold),
            __('messages.inventory_hold.released')
        );
    }
}
