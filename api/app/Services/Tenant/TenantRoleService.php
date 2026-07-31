<?php

namespace App\Services\Tenant;

use App\DTOs\Tenant\CreateTenantRoleDTO;
use App\DTOs\Tenant\UpdateTenantRoleDTO;
use App\Events\Tenant\TenantRoleCreated;
use App\Events\Tenant\TenantRoleUpdated;
use App\Events\Tenant\TenantRoleDeleted;
use App\Models\Tenant\TenantRole;
use App\Repositories\Contracts\TenantRoleRepositoryInterface;
use App\Services\Permission\PermissionService;
use App\Support\GridQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TenantRoleService
{
    /**
     * Slug fixo que identifica o perfil "Proprietário" auto-provisionado
     * por TenantProvisioningService::createOwnerRole() em toda tenant —
     * não existe coluna dedicada (ex.: is_protected/is_system); optamos por
     * comparar slug para manter simples, já que é assim que o role é
     * identificado em todo o resto do sistema hoje (ver
     * TenantProvisioningService).
     */
    private const PROTECTED_ROLE_SLUG = 'owner';

    public function __construct(
        private TenantRoleRepositoryInterface $repository,
        private PermissionService $permissionService
    ) {
    }

    public function paginate(
        int $tenantId,
        int $perPage = 15,
        array $filters = [],
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator
    {
        $sortable = [
            'name' => 'tenant_roles.name',
            'slug' => 'tenant_roles.slug',
            'is_active' => 'tenant_roles.is_active',
        ];

        $query = TenantRole::query()
            ->select(['tenant_roles.id', 'tenant_roles.uuid', 'tenant_roles.name', 'tenant_roles.slug', 'tenant_roles.is_active', 'tenant_roles.created_at'])
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ;

        GridQuery::applyTextFilters($query, $filters, [
            'name' => 'tenant_roles.name',
            'slug' => 'tenant_roles.slug',
        ]);

        GridQuery::applyBooleanFilters($query, $filters, [
            'is_active' => 'tenant_roles.is_active',
        ]);

        $sortColumn = is_string($sortBy) ? ($sortable[$sortBy] ?? null) : null;
        $query->orderBy($sortColumn ?? 'tenant_roles.name', GridQuery::normalizeSortDir($sortDir));

        return $query->paginate($perPage);
    }

    public function create(CreateTenantRoleDTO $dto): TenantRole
    {
        return DB::transaction(function () use ($dto) {

            if ($this->repository->slugExists($dto->tenantId, $dto->slug)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.tenant_role.slug_exists'));
            }

            $role = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'name' => $dto->name,
                'slug' => $dto->slug,
                'is_active' => $dto->isActive,
            ]);

            event(new TenantRoleCreated(
                tenantRoleUuid: $role->uuid,
                actorId: Auth::id()
            ));

            return $role;
        });
    }

    public function update(TenantRole $role, UpdateTenantRoleDTO $dto): TenantRole
    {
        $this->assertBelongsToCurrentTenant($role);
        $this->assertOwnerRoleEditableByActor($role);

        return DB::transaction(function () use ($role, $dto) {

            $original = $role->getOriginal();

            if ($dto->slug && $this->repository->slugExists($role->tenant_id, $dto->slug, $role->id)) {
                throw new \App\Exceptions\DuplicateNameException(__('messages.tenant_role.slug_exists'));
            }

            $data = array_filter([
                'name' => $dto->name,
                'slug' => $dto->slug,
                'is_active' => $dto->isActive,
            ], fn($v) => !is_null($v));

            if (!empty($data)) {
                $role = $this->repository->update($role, $data);

                $changes = array_diff_assoc($role->getAttributes(), $original);

                event(new TenantRoleUpdated(
                    tenantRoleUuid: $role->uuid,
                    actorId: Auth::id(),
                    changes: array_keys($changes)
                ));
            }

            return $role;
        });
    }

    public function delete(TenantRole $role): void
    {
        $this->assertBelongsToCurrentTenant($role);
        $this->assertOwnerRoleNotDeletable($role);

        DB::transaction(function () use ($role) {
            $this->repository->delete($role);

            event(new TenantRoleDeleted(
                tenantRoleUuid: $role->uuid,
                actorId: Auth::id()
            ));
        });
    }

    /**
     * Route-model-binding resolve só por uuid, sem escopo de tenant — sem
     * esta checagem, um usuário com permissão poderia mutar registro de
     * outro tenant só sabendo o uuid (IDOR).
     */
    private function assertBelongsToCurrentTenant(TenantRole $role): void
    {
        if ((int) $role->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }

    /**
     * O perfil "owner" só pode ser editado (nome/slug/is_active) por um
     * administrador da plataforma PegaTicket (grupo global 'administrators').
     * Qualquer usuário do grupo 'clients' — inclusive o próprio dono da
     * empresa autenticado na própria tenant, que tecnicamente possui
     * tenant_roles:update — fica bloqueado, para impedir enfraquecimento
     * acidental ou deliberado do próprio perfil que sustenta seu acesso.
     */
    private function assertOwnerRoleEditableByActor(TenantRole $role): void
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
     * Exclusão do perfil "owner" é bloqueada incondicionalmente, inclusive
     * para administrador da plataforma: apagar esse role quebra o acesso
     * do dono da empresa a uma tenant ativa, sem benefício correspondente
     * (diferente de editar nome/permissões, não existe cenário legítimo
     * para excluir o único role que sustenta o proprietário).
     */
    private function assertOwnerRoleNotDeletable(TenantRole $role): void
    {
        if ($role->slug === self::PROTECTED_ROLE_SLUG) {
            throw new \App\Exceptions\ProtectedTenantRoleException(
                __('messages.tenant_role.protected_owner_role')
            );
        }
    }
}
