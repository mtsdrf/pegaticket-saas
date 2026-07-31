<?php

namespace App\Http\Controllers\Fiscal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\StoreTaxRuleRequest;
use App\Http\Requests\Fiscal\UpdateTaxRuleRequest;
use App\Http\Resources\Fiscal\TaxRuleResource;
use App\Models\Fiscal\TaxRule;
use App\Services\APIResponse;
use Illuminate\Support\Str;

class TaxRuleController extends Controller
{
    public function index()
    {
        $rules = TaxRule::query()
            ->where('tenant_id', app('tenant_id'))
            ->whereNull('deleted_at')
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->get();

        return APIResponse::success(TaxRuleResource::collection($rules), __('messages.tax_rule.list'));
    }

    public function store(StoreTaxRuleRequest $request)
    {
        $rule = TaxRule::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => app('tenant_id'),
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return APIResponse::success(new TaxRuleResource($rule), __('messages.tax_rule.created'), 201);
    }

    public function update(UpdateTaxRuleRequest $request, TaxRule $taxRule)
    {
        abort_unless((int) $taxRule->tenant_id === (int) app('tenant_id'), 404);

        $data = $request->validated();
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $taxRule->fill($data);
        $taxRule->save();

        return APIResponse::success(new TaxRuleResource($taxRule), __('messages.tax_rule.updated'));
    }

    public function destroy(TaxRule $taxRule)
    {
        abort_unless((int) $taxRule->tenant_id === (int) app('tenant_id'), 404);

        $taxRule->delete();

        return APIResponse::success(null, __('messages.tax_rule.deleted'), 204);
    }
}
