<?php

namespace App\Http\Controllers\Accounting;

use App\DTOs\Fiscal\CreateTaxRuleDTO;
use App\DTOs\Fiscal\UpdateTaxRuleDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\TaxRuleRequest;
use App\Http\Resources\Fiscal\TaxRuleResource;
use App\Models\AuditLog;
use App\Models\Fiscal\TaxRule;
use App\Services\APIResponse;

class AccountingTaxRuleController extends Controller
{
    public function index()
    {
        $rules = TaxRule::query()
            ->where('tenant_id', app('tenant_id'))
            ->whereNull('deleted_at')
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->get();

        return APIResponse::success(
            TaxRuleResource::collection($rules),
            __('messages.tax_rule.list')
        );
    }

    public function store(TaxRuleRequest $request)
    {
        $dto = CreateTaxRuleDTO::fromArray($request->validated());

        $rule = TaxRule::create([
            'tenant_id' => app('tenant_id'),
            'tax_type' => $dto->taxType,
            'scope' => $dto->scope,
            'rate_percent' => $dto->ratePercent,
            'valid_from' => $dto->validFrom,
            'valid_to' => $dto->validTo,
            'is_active' => $dto->isActive,
        ]);

        $this->audit('created_tax_rule', $rule->uuid, array_keys($request->validated()));

        return APIResponse::success(
            new TaxRuleResource($rule),
            __('messages.tax_rule.created'),
            201
        );
    }

    public function update(string $uuid, TaxRuleRequest $request)
    {
        $dto = UpdateTaxRuleDTO::fromArray($request->validated());
        $rule = $this->findScopedOrFail($uuid);

        $rule->fill([
            'tax_type' => $dto->taxType,
            'scope' => $dto->scope,
            'rate_percent' => $dto->ratePercent,
            'valid_from' => $dto->validFrom,
            'valid_to' => $dto->validTo,
            'is_active' => $dto->isActive,
        ])->save();

        $this->audit('updated_tax_rule', $rule->uuid, array_keys($request->validated()));

        return APIResponse::success(
            new TaxRuleResource($rule),
            __('messages.tax_rule.updated')
        );
    }

    public function destroy(string $uuid)
    {
        $rule = $this->findScopedOrFail($uuid);
        $rule->delete();

        $this->audit('deleted_tax_rule', $uuid, []);

        return APIResponse::success(
            null,
            __('messages.tax_rule.deleted'),
            204
        );
    }

    private function findScopedOrFail(string $uuid): TaxRule
    {
        return TaxRule::query()
            ->where('tenant_id', app('tenant_id'))
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    private function audit(string $action, string $ruleUuid, array $changedFields): void
    {
        $office = accounting_office();

        AuditLog::recordForNonUser('accounting_office.' . $action, [
            'accounting_office_id' => $office?->id,
            'accounting_office_uuid' => $office?->uuid,
            'tenant_id' => app('tenant_id'),
            'tax_rule_uuid' => $ruleUuid,
            'changed_fields' => $changedFields,
        ]);
    }
}
