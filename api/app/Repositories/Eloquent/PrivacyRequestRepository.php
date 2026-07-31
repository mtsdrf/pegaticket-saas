<?php

namespace App\Repositories\Eloquent;

use App\Models\Privacy\PrivacyRequest;
use App\Repositories\Contracts\PrivacyRequestRepositoryInterface;
use Illuminate\Support\Collection;

class PrivacyRequestRepository extends BaseRepository implements PrivacyRequestRepositoryInterface
{
    public function __construct(PrivacyRequest $model)
    {
        parent::__construct($model);
    }

    public function listForTenant(int $tenantId, int $limit = 50): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function findForTenantByUuid(int $tenantId, string $uuid): ?PrivacyRequest
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('uuid', $uuid)
            ->first();
    }
}
