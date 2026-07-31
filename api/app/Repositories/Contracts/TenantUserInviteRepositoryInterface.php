<?php

namespace App\Repositories\Contracts;

use App\Models\Tenant\TenantUserInvite;

interface TenantUserInviteRepositoryInterface extends BaseRepositoryInterface
{
    public function findPendingByTenantAndEmail(int $tenantId, string $email): ?TenantUserInvite;

    public function findByTokenHash(string $tokenHash): ?TenantUserInvite;
}
