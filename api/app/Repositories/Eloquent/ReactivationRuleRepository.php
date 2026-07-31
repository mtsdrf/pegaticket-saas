<?php

namespace App\Repositories\Eloquent;

use App\Models\Storefront\ReactivationRule;
use App\Repositories\Contracts\ReactivationRuleRepositoryInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

class ReactivationRuleRepository extends BaseRepository implements ReactivationRuleRepositoryInterface
{
    public function __construct(ReactivationRule $model)
    {
        parent::__construct($model);
    }

    public function findOrCreateForTenant(int $tenantId): ReactivationRule
    {
        $rule = $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->first();

        if ($rule) {
            return $rule;
        }

        try {
            return $this->model->create([
                'tenant_id' => $tenantId,
                'days_without_order' => 30,
                'coupon_type' => 'percentage',
                'coupon_value' => 10,
                'coupon_validity_days' => 7,
                'is_active' => false,
            ]);
        } catch (QueryException $e) {
            // Corrida entre duas primeiras leituras concorrentes — a
            // unique (uniq_reactivation_rule_tenant) já garantiu 1 linha,
            // só falta buscar a que o outro request/comando criou.
            return $this->model
                ->whereNull('deleted_at')
                ->where('tenant_id', $tenantId)
                ->firstOrFail();
        }
    }

    public function listActive(): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->get();
    }
}
