<?php

namespace App\Repositories\Eloquent;

use App\Models\Fiscal\TaxRule;
use App\Repositories\Contracts\TaxRuleRepositoryInterface;
use Illuminate\Support\Collection;

class TaxRuleRepository extends BaseRepository implements TaxRuleRepositoryInterface
{
    public function __construct(TaxRule $model)
    {
        parent::__construct($model);
    }

    public function listForTenant(int $tenantId): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->orderBy('tax_type')
            ->orderByDesc('id')
            ->get();
    }
}
