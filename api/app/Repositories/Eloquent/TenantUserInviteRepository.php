<?php

namespace App\Repositories\Eloquent;

use App\Models\Tenant\TenantUserInvite;
use App\Repositories\Contracts\TenantUserInviteRepositoryInterface;

class TenantUserInviteRepository extends BaseRepository implements TenantUserInviteRepositoryInterface
{
    public function __construct(TenantUserInvite $model)
    {
        parent::__construct($model);
    }

    public function findPendingByTenantAndEmail(int $tenantId, string $email): ?TenantUserInvite
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function findByTokenHash(string $tokenHash): ?TenantUserInvite
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('token_hash', $tokenHash)
            ->first();
    }
}
