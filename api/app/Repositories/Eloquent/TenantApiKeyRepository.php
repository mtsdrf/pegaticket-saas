<?php

namespace App\Repositories\Eloquent;

use App\Models\ApiKey\TenantApiKey;
use App\Repositories\Contracts\TenantApiKeyRepositoryInterface;
use Illuminate\Support\Collection;

class TenantApiKeyRepository extends BaseRepository implements TenantApiKeyRepositoryInterface
{
    public function __construct(TenantApiKey $model)
    {
        parent::__construct($model);
    }

    public function listForTenant(int $tenantId): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->get();
    }

    public function findActiveByHash(string $keyHash): ?TenantApiKey
    {
        return $this->model
            ->whereNull('deleted_at')
            ->whereNull('revoked_at')
            ->where('key_hash', $keyHash)
            ->first();
    }
}
