<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\ListRefundsRequest;
use App\Http\Resources\Subscription\RefundResource;
use App\Services\APIResponse;
use App\Services\Subscription\RefundService;

/**
 * Visão do PRÓPRIO tenant/proprietário sobre seus estornos (roadmap
 * 2026-07-24) — tenant-scoped, `perm:subscription,read`, mesma
 * functionality já usada pelo resto do módulo de assinatura.
 */
class RefundController extends Controller
{
    public function __construct(
        private RefundService $service
    ) {
    }

    public function index(ListRefundsRequest $request)
    {
        $validated = $request->validated();

        $list = $this->service->listForTenant(app('tenant_id'), (int) ($validated['per_page'] ?? 15));

        return APIResponse::success(
            RefundResource::collection($list->items()),
            __('messages.subscription.refunds_listed'),
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
}
