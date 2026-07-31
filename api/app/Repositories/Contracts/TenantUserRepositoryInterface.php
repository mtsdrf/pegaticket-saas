<?php

namespace App\Repositories\Contracts;

use App\Models\Tenant\TenantUser;
use App\Models\User\User;
use Illuminate\Support\Collection;

interface TenantUserRepositoryInterface extends BaseRepositoryInterface
{
    public function getUserTenants(int $userId): Collection;

    public function findByTenantAndUser(int $tenantId, int $userId): ?TenantUser;

    /**
     * Usuário com o perfil "owner" (tenant_roles.slug=owner) ativo no
     * tenant. Fonte única de verdade de "quem é o dono desta empresa" —
     * mesmo critério já usado por PermissionService::userIsTenantOwner(),
     * mas retornando o User em vez de bool (usado para resolver o payer
     * real da cobrança de assinatura no Mercado Pago).
     */
    public function findOwnerUserForTenant(int $tenantId): ?User;
}