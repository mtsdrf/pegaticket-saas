<?php

namespace App\Repositories\Eloquent;

use App\Models\Report\ScheduledReportSubscription;
use App\Repositories\Contracts\ScheduledReportSubscriptionRepositoryInterface;
use Illuminate\Support\Collection;

class ScheduledReportSubscriptionRepository extends BaseRepository implements ScheduledReportSubscriptionRepositoryInterface
{
    public function __construct(ScheduledReportSubscription $model)
    {
        parent::__construct($model);
    }

    public function allForTenant(int $tenantId): Collection
    {
        return ScheduledReportSubscription::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get();
    }

    public function findForTenantOrFail(int $tenantId, string $uuid): ScheduledReportSubscription
    {
        return ScheduledReportSubscription::query()
            ->where('tenant_id', $tenantId)
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }
}
