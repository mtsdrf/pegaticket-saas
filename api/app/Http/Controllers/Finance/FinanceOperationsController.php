<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinanceDashboardRequest;
use App\Http\Requests\Finance\ReceivableIndexRequest;
use App\Http\Requests\Finance\SettlementIndexRequest;
use App\Http\Resources\Finance\FinanceDashboardResource;
use App\Http\Resources\Finance\ReceivableResource;
use App\Http\Resources\Finance\SettlementResource;
use App\Services\APIResponse;
use App\Services\Finance\FinanceOperationsService;

class FinanceOperationsController extends Controller
{
    public function __construct(
        private FinanceOperationsService $service,
    ) {}

    public function dashboard(FinanceDashboardRequest $request)
    {
        return APIResponse::success(
            new FinanceDashboardResource($this->service->dashboard(app('tenant_id'), $request->validated())),
            __('messages.finance.dashboard_loaded')
        );
    }

    public function receivables(ReceivableIndexRequest $request)
    {
        $validated = $request->validated();
        $list = $this->service->receivables(
            app('tenant_id'),
            $validated,
            (int) ($validated['per_page'] ?? 15),
        );

        return APIResponse::success(
            ReceivableResource::collection($list->items()),
            __('messages.finance.receivables_listed'),
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

    public function receivablesSummary(ReceivableIndexRequest $request)
    {
        return APIResponse::success(
            $this->service->receivablesSummary(app('tenant_id'), $request->validated()),
            __('messages.finance.receivables_summary')
        );
    }

    public function settlements(SettlementIndexRequest $request)
    {
        $validated = $request->validated();
        $list = $this->service->settlements(
            app('tenant_id'),
            $validated,
            (int) ($validated['per_page'] ?? 15),
        );

        return APIResponse::success(
            SettlementResource::collection($list->items()),
            __('messages.finance.settlements_listed'),
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

    public function settlementsSummary(SettlementIndexRequest $request)
    {
        return APIResponse::success(
            $this->service->settlementsSummary(app('tenant_id'), $request->validated()),
            __('messages.finance.settlements_summary')
        );
    }
}
