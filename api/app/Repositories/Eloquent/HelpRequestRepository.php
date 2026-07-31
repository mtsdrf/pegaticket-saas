<?php

namespace App\Repositories\Eloquent;

use App\Models\Support\HelpRequest;
use App\Repositories\Contracts\HelpRequestRepositoryInterface;
use Illuminate\Support\Collection;

class HelpRequestRepository extends BaseRepository implements HelpRequestRepositoryInterface
{
    public function __construct(HelpRequest $model)
    {
        parent::__construct($model);
    }

    public function listForTenant(int $tenantId, int $limit = 50): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
