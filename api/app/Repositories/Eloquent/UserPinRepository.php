<?php

namespace App\Repositories\Eloquent;

use App\Models\Pdv\UserPin;
use App\Repositories\Contracts\UserPinRepositoryInterface;

class UserPinRepository extends BaseRepository implements UserPinRepositoryInterface
{
    public function __construct(UserPin $model)
    {
        parent::__construct($model);
    }

    public function findForTenantUser(int $tenantId, int $userId): ?UserPin
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();
    }

    public function findByTenantAndHash(int $tenantId, string $pinHash): ?UserPin
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('pin_hash', $pinHash)
            ->first();
    }

    public function hashExistsForTenant(int $tenantId, string $pinHash, ?int $excludeUserId = null): bool
    {
        $query = $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('pin_hash', $pinHash);

        if ($excludeUserId) {
            $query->where('user_id', '!=', $excludeUserId);
        }

        return $query->exists();
    }
}
