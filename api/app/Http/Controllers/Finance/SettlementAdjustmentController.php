<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ResolveSettlementAdjustmentRequest;
use App\Http\Requests\Finance\SettlementAdjustmentIndexRequest;
use App\Http\Requests\Finance\StoreManualSettlementAdjustmentRequest;
use App\Http\Resources\Finance\SettlementAdjustmentResource;
use App\Services\APIResponse;
use App\Services\Finance\SettlementAdjustmentWorkflowService;

class SettlementAdjustmentController extends Controller
{
    public function __construct(
        private SettlementAdjustmentWorkflowService $service,
    ) {}

    public function index(SettlementAdjustmentIndexRequest $request)
    {
        $validated = $request->validated();

        $list = $this->service->list(
            app('tenant_id'),
            $validated,
            (int) ($validated['per_page'] ?? 15),
        );

        return APIResponse::success(
            SettlementAdjustmentResource::collection($list->items()),
            __('messages.finance.adjustments_listed'),
            200,
            [
                'pagination' => [
                    'current_page' => $list->currentPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'last_page' => $list->lastPage(),
                ],
            ]
        );
    }

    public function summary(SettlementAdjustmentIndexRequest $request)
    {
        return APIResponse::success(
            $this->service->summary(app('tenant_id'), $request->validated()),
            __('messages.finance.adjustments_summary')
        );
    }

    public function storeManual(StoreManualSettlementAdjustmentRequest $request)
    {
        return APIResponse::success(
            new SettlementAdjustmentResource($this->service->createManual(app('tenant_id'), $request->validated())),
            __('messages.finance.adjustment_manual_created'),
            201
        );
    }

    public function resolveRecovery(string $uuid, ResolveSettlementAdjustmentRequest $request)
    {
        return APIResponse::success(
            new SettlementAdjustmentResource($this->service->resolvePendingRecovery(app('tenant_id'), $uuid, $request->validated())),
            __('messages.finance.adjustment_recovery_resolved')
        );
    }

    public function resolveReview(string $uuid, ResolveSettlementAdjustmentRequest $request)
    {
        return APIResponse::success(
            new SettlementAdjustmentResource($this->service->resolvePendingReview(app('tenant_id'), $uuid, $request->validated())),
            __('messages.finance.adjustment_review_resolved')
        );
    }
}
