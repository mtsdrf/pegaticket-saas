<?php

namespace App\Repositories\Eloquent;

use App\Models\Subscription\Subscription;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SubscriptionRepository extends BaseRepository implements SubscriptionRepositoryInterface
{
    public function __construct(Subscription $model)
    {
        parent::__construct($model);
    }

    /**
     * Assinatura "atual" do tenant — a mais recente não deletada. Um tenant
     * pode ter histórico (assinatura cancelada + nova), então pega a de
     * maior id.
     */
    public function findCurrentForTenant(int $tenantId): ?Subscription
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->first();
    }

    public function findByUuidForTenant(string $uuid, int $tenantId): ?Subscription
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('uuid', $uuid)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    public function paginateForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->with('plan')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function pastGracePeriod(): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<=', now())
            ->whereNotIn('status', ['suspended', 'canceled'])
            ->get();
    }
}
