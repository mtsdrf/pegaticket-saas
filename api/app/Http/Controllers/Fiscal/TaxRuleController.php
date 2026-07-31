<?php

namespace App\Http\Controllers\Fiscal;

use App\DTOs\Fiscal\CreateTaxRuleDTO;
use App\DTOs\Fiscal\UpdateTaxRuleDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\TaxRuleRequest;
use App\Http\Resources\Fiscal\TaxRuleResource;
use App\Services\APIResponse;
use App\Services\Fiscal\TaxRuleService;

class TaxRuleController extends Controller
{
    public function __construct(
        private TaxRuleService $service
    ) {
    }

    public function index()
    {
        $rules = $this->service->list(app('tenant_id'));

        return APIResponse::success(
            TaxRuleResource::collection($rules),
            __('messages.tax_rule.list')
        );
    }

    public function store(TaxRuleRequest $request)
    {
        $dto = CreateTaxRuleDTO::fromArray($request->validated());

        $taxRule = $this->service->create(app('tenant_id'), $dto);

        return APIResponse::success(
            new TaxRuleResource($taxRule),
            __('messages.tax_rule.created'),
            201
        );
    }

    public function update(string $uuid, TaxRuleRequest $request)
    {
        $dto = UpdateTaxRuleDTO::fromArray($request->validated());

        $taxRule = $this->service->update(app('tenant_id'), $uuid, $dto);

        return APIResponse::success(
            new TaxRuleResource($taxRule),
            __('messages.tax_rule.updated')
        );
    }

    public function destroy(string $uuid)
    {
        $this->service->delete(app('tenant_id'), $uuid);

        return APIResponse::success(
            null,
            __('messages.tax_rule.deleted'),
            204
        );
    }
}
