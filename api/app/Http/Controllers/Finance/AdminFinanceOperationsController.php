<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\AdminAdjustmentIndexRequest;
use App\Http\Requests\Finance\AdminFinanceDashboardRequest;
use App\Http\Requests\Finance\AdminReceivableIndexRequest;
use App\Http\Requests\Finance\AdminSettlementIndexRequest;
use App\Http\Resources\Finance\FinanceDashboardResource;
use App\Http\Resources\Finance\ReceivableResource;
use App\Http\Resources\Finance\SettlementAdjustmentResource;
use App\Http\Resources\Finance\SettlementResource;
use App\Services\APIResponse;
use App\Services\Finance\AdminFinanceOperationsService;

class AdminFinanceOperationsController extends Controller
{
    public function __construct(
        private AdminFinanceOperationsService $service,
    ) {}

    public function dashboard(AdminFinanceDashboardRequest $request)
    {
        return APIResponse::success(
            new FinanceDashboardResource($this->service->dashboard($request->validated())),
            __('messages.finance.admin_dashboard_loaded')
        );
    }

    public function receivables(AdminReceivableIndexRequest $request)
    {
        $validated = $request->validated();
        $list = $this->service->receivables($validated, (int) ($validated['per_page'] ?? 15));

        return APIResponse::success(
            ReceivableResource::collection($list->items()),
            __('messages.finance.admin_receivables_listed'),
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

    public function settlements(AdminSettlementIndexRequest $request)
    {
        $validated = $request->validated();
        $list = $this->service->settlements($validated, (int) ($validated['per_page'] ?? 15));

        return APIResponse::success(
            SettlementResource::collection($list->items()),
            __('messages.finance.admin_settlements_listed'),
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

    public function adjustments(AdminAdjustmentIndexRequest $request)
    {
        $validated = $request->validated();
        $list = $this->service->adjustments($validated, (int) ($validated['per_page'] ?? 15));

        return APIResponse::success(
            SettlementAdjustmentResource::collection($list->items()),
            __('messages.finance.admin_adjustments_listed'),
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
}
