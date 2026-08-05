<?php

namespace App\Repositories\Contracts;

use App\Models\Report\ScheduledReportSubscription;
use Illuminate\Support\Collection;

interface ScheduledReportSubscriptionRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Collection<int, ScheduledReportSubscription>
     */
    public function allForTenant(int $tenantId): Collection;

    public function findForTenantOrFail(int $tenantId, string $uuid): ScheduledReportSubscription;
}
