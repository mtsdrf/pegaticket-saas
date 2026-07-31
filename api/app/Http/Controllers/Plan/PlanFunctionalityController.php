<?php

namespace App\Http\Controllers\Plan;

use App\DTOs\Plan\SyncPlanFunctionalitiesDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Plan\SyncPlanFunctionalitiesRequest;
use App\Models\Plan\Plan;
use App\Services\APIResponse;
use App\Services\Plan\PlanFunctionalityService;

class PlanFunctionalityController extends Controller
{
    public function __construct(
        private PlanFunctionalityService $service
    ) {
    }

    public function index(Plan $plan)
    {
        $functionalities = $this->service->getFunctionalities($plan);

        return APIResponse::success(
            $functionalities,
            __('messages.plan_functionality.list')
        );
    }

    public function sync(SyncPlanFunctionalitiesRequest $request, Plan $plan)
    {
        $dto = SyncPlanFunctionalitiesDTO::fromArray($request->validated());
        $this->service->syncFunctionalities($plan, $dto);

        return APIResponse::success(
            null,
            __('messages.plan_functionality.synced')
        );
    }
}
