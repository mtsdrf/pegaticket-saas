<?php

namespace App\Http\Controllers\Plan;

use App\DTOs\Plan\CreatePlanDTO;
use App\DTOs\Plan\UpdatePlanDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Plan\StorePlanRequest;
use App\Http\Requests\Plan\UpdatePlanRequest;
use App\Http\Resources\Plan\PlanResource;
use App\Models\Plan\Plan;
use App\Services\APIResponse;
use App\Services\Plan\PlanService;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function __construct(
        private PlanService $service
    ) {
    }

    public function index(Request $request)
    {
        $list = $this->service->paginate(
            (int) $request->get('per_page', 15),
            $request->only(['name', 'slug', 'description', 'is_active']),
            $request->get('sort_by'),
            (string) $request->get('sort_dir', 'asc')
        );

        return APIResponse::success(
            PlanResource::collection($list),
            __('messages.plan.list'),
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

    public function store(StorePlanRequest $request)
    {
        $dto = CreatePlanDTO::fromArray($request->validated());
        $plan = $this->service->create($dto);

        return APIResponse::success(
            new PlanResource($plan),
            __('messages.plan.created'),
            201
        );
    }

    public function show(Plan $plan)
    {
        return APIResponse::success(
            new PlanResource($plan),
            __('messages.plan.show')
        );
    }

    public function update(UpdatePlanRequest $request, Plan $plan)
    {
        $dto = UpdatePlanDTO::fromArray($request->validated());
        $plan = $this->service->update($plan, $dto);

        return APIResponse::success(
            new PlanResource($plan),
            __('messages.plan.updated')
        );
    }

    public function destroy(Plan $plan)
    {
        $this->service->delete($plan);

        return APIResponse::success(
            null,
            __('messages.plan.deleted'),
            204
        );
    }
}
