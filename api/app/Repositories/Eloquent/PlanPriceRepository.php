<?php

namespace App\Repositories\Eloquent;

use App\Models\Subscription\PlanPrice;
use App\Repositories\Contracts\PlanPriceRepositoryInterface;

class PlanPriceRepository extends BaseRepository implements PlanPriceRepositoryInterface
{
    public function __construct(PlanPrice $model)
    {
        parent::__construct($model);
    }

    public function findActive(int $planId, string $billingPeriod): ?PlanPrice
    {
        $now = now();

        return $this->model
            ->whereNull('deleted_at')
            ->where('plan_id', $planId)
            ->where('billing_period', $billingPeriod)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $now))
            ->orderByDesc('id')
            ->first();
    }
}
