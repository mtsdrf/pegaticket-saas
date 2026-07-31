<?php

namespace App\Repositories\Contracts;

use App\Models\Pdv\UserPin;

interface UserPinRepositoryInterface extends BaseRepositoryInterface
{
    public function findForTenantUser(int $tenantId, int $userId): ?UserPin;

    public function findByTenantAndHash(int $tenantId, string $pinHash): ?UserPin;

    public function hashExistsForTenant(int $tenantId, string $pinHash, ?int $excludeUserId = null): bool;
}
