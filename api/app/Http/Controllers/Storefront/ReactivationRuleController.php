<?php

namespace App\Http\Controllers\Storefront;

use App\DTOs\Storefront\UpdateReactivationRuleDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\UpdateReactivationRuleRequest;
use App\Http\Resources\Storefront\ReactivationRuleResource;
use App\Services\APIResponse;
use App\Services\Storefront\ReactivationRuleService;

class ReactivationRuleController extends Controller
{
    public function __construct(
        private ReactivationRuleService $service
    ) {
    }

    public function show()
    {
        $rule = $this->service->getForTenant(app('tenant_id'));

        return APIResponse::success(
            new ReactivationRuleResource($rule),
            __('messages.reactivation_rule.show')
        );
    }

    public function update(UpdateReactivationRuleRequest $request)
    {
        $dto = UpdateReactivationRuleDTO::fromArray($request->validated());

        $rule = $this->service->update(app('tenant_id'), $dto);

        return APIResponse::success(
            new ReactivationRuleResource($rule),
            __('messages.reactivation_rule.updated')
        );
    }
}
