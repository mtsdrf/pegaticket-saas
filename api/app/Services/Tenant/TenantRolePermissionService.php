<?php

namespace App\Services\Tenant;

use App\DTOs\Tenant\SyncTenantRolePermissionsDTO;
use App\Events\Tenant\TenantRolePermissionsSynced;
use App\Models\Tenant\TenantRole;
use App\Repositories\Contracts\TenantRolePermissionRepositoryInterface;
use App\Services\Permission\PermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TenantRolePermissionService
{
    /** @see TenantRoleService::PROTECTED_ROLE_SLUG */
    private const PROTECTED_ROLE_SLUG = 'owner';

    public function __construct(
        private TenantRolePermissionRepositoryInterface $repository,
        private PermissionService $permissionService
    ) {
    }

    public function getPermissions(TenantRole $role)
    {
        $this->assertBelongsToCurrentTenant($role);

        return $this->repository->getRolePermissions($role->id);
    }

    public function syncPermissions(
        TenantRole $role,
        SyncTenantRolePermissionsDTO $dto
    ): void {
        $this->assertBelongsToCurrentTenant($role);
        $this->assertOwnerPermissionsEditableByActor($role);

        DB::transaction(function () use ($role, $dto) {

            $this->repository->syncPermissions(
                $role->id,
                $dto->permissions
            );

            event(new TenantRolePermissionsSynced(
                tenantRoleUuid: $role->uuid,
                actorId: Auth::id()
            ));
        });
    }

    /**
     * Núcleo do bloqueia venda: só administrador da plataforma
     * ('administrators') pode alterar as permissões do perfil "owner" de
     * uma tenant. O dono da empresa (grupo global 'clients'), mesmo tendo
     * tenant_roles:update na própria tenant, não pode enfraquecer/alterar
     * as permissões do próprio perfil "owner". Sync de permissões de
     * qualquer outro perfil (inclusive novos perfis criados pelo dono)
     * continua liberado normalmente.
     */
    private function assertOwnerPermissionsEditableByActor(TenantRole $role): void
    {
        if ($role->slug !== self::PROTECTED_ROLE_SLUG) {
            return;
        }

        if ($this->permissionService->userHasGroupSlug((int) Auth::id(), 'administrators')) {
            return;
        }

        throw new \App\Exceptions\ProtectedTenantRoleException(
            __('messages.tenant_role.protected_owner_role')
        );
    }

    /**
     * Route-model-binding resolve só por uuid, sem escopo de tenant — sem
     * esta checagem, um usuário com tenant_roles:update na própria tenant
     * poderia ler/alterar permissões de um perfil de outra tenant só
     * sabendo o uuid (IDOR). Mesmo guard já usado em
     * TenantRoleService::assertBelongsToCurrentTenant().
     */
    private function assertBelongsToCurrentTenant(TenantRole $role): void
    {
        if ((int) $role->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}