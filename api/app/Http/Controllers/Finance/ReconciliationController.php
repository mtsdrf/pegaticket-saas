<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ReconciliationRequest;
use App\Http\Resources\Finance\ReconciliationEntryResource;
use App\Services\APIResponse;
use App\Services\Finance\ReconciliationService;

class ReconciliationController extends Controller
{
    public function __construct(
        private ReconciliationService $service
    ) {
    }

    public function index(ReconciliationRequest $request)
    {
        $validated = $request->validated();

        $list = $this->service->list(
            app('tenant_id'),
            $validated,
            (int) ($validated['per_page'] ?? 15)
        );

        return APIResponse::success(
            ReconciliationEntryResource::collection($list->items()),
            __('messages.finance.reconciliation_listed'),
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

    public function summary(ReconciliationRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->summary(app('tenant_id'), $validated);

        return APIResponse::success($data, __('messages.finance.reconciliation_summary'));
    }
}
