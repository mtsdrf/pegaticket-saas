<?php

namespace App\Services\Tenant;

use App\DTOs\Tenant\CreateTenantUserDTO;
use App\DTOs\Tenant\UpdateTenantUserDTO;
use App\DTOs\User\CreateUserDTO;
use App\Events\Tenant\TenantUserCreated;
use App\Events\Tenant\TenantUserUpdated;
use App\Events\Tenant\TenantUserDeleted;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantRole;
use App\Models\Tenant\TenantUser;
use App\Models\User\User;
use App\Repositories\Contracts\TenantUserRepositoryInterface;
use App\Services\User\UserService;
use App\Support\GridQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TenantUserService
{
    public function __construct(
        private TenantUserRepositoryInterface $repository,
        private UserService $userService
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
            'user_name' => 'users.name',
            'tenant_name' => 'tenants.name',
            'role_name' => 'tenant_roles.name',
            'is_active' => 'tenant_users.is_active',
        ];

        $query = TenantUser::query()
            ->select(['tenant_users.*'])
            ->join('users', 'users.id', '=', 'tenant_users.user_id')
            ->join('tenants', 'tenants.id', '=', 'tenant_users.tenant_id')
            ->join('tenant_roles', 'tenant_roles.id', '=', 'tenant_users.tenant_role_id')
            ->with(['tenant', 'user', 'role'])
            ->where('tenant_users.tenant_id', $tenantId)
            ->whereNull('tenant_users.deleted_at');

        GridQuery::applyTextFilters($query, $filters, [
            'user_name' => 'users.name',
            'tenant_name' => 'tenants.name',
            'role_name' => 'tenant_roles.name',
        ]);

        GridQuery::applyBooleanFilters($query, $filters, [
            'is_active' => 'tenant_users.is_active',
        ]);

        $sortColumn = is_string($sortBy) ? ($sortable[$sortBy] ?? null) : null;
        $query->orderBy($sortColumn ?? 'users.name', GridQuery::normalizeSortDir($sortDir));

        return $query->paginate($perPage);
    }

    /**
     * @param int|null $actorId Override para Auth::id() quando chamado fora
     *                          de um contexto autenticado (ex: aceite de
     *                          convite público — AcceptTenantUserInviteService,
     *                          onde o próprio usuário recém-criado é o ator).
     *                          Mesmo padrão de TenantService::create().
     */
    public function create(CreateTenantUserDTO $dto, ?int $actorId = null): TenantUser
    {
        return DB::transaction(function () use ($dto, $actorId) {
            if ((string) app('tenant_uuid') !== $dto->tenantUuid) {
                abort(404);
            }

            $tenant = Tenant::where('uuid', $dto->tenantUuid)->firstOrFail();
            $role = TenantRole::where('uuid', $dto->roleUuid)
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            $user = $dto->userUuid
                ? User::where('uuid', $dto->userUuid)->firstOrFail()
                : $this->userService->create(new CreateUserDTO(
                    name: (string) $dto->userName,
                    email: (string) $dto->userEmail,
                    password: (string) $dto->userPassword,
                    isActive: $dto->isActive,
                    groupUuids: []
                ));

            if ($this->repository->findByTenantAndUser($tenant->id, $user->id)) {
                throw new \RuntimeException(__('messages.tenant_user.already_exists'));
            }

            $tenantUser = $this->repository->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'tenant_role_id' => $role->id,
                'is_active' => $dto->isActive,
            ]);

            event(new TenantUserCreated(
                tenantUserUuid: $tenantUser->uuid,
                actorId: $actorId ?? Auth::id() ?? $user->id
            ));

            return $tenantUser;
        });
    }

    public function update(TenantUser $tenantUser, UpdateTenantUserDTO $dto): TenantUser
    {
        return DB::transaction(function () use ($tenantUser, $dto) {
            $this->assertBelongsToCurrentTenant($tenantUser);

            $original = $tenantUser->getOriginal();

            $data = [];

            if ($dto->roleUuid) {
                $role = TenantRole::where('uuid', $dto->roleUuid)
                    ->where('tenant_id', $tenantUser->tenant_id)
                    ->firstOrFail();

                $data['tenant_role_id'] = $role->id;
            }

            if (!is_null($dto->isActive)) {
                $data['is_active'] = $dto->isActive;
            }

            if (!empty($data)) {
                $tenantUser = $this->repository->update($tenantUser, $data);

                $changes = array_diff_assoc(
                    $tenantUser->getAttributes(),
                    $original
                );

                event(new TenantUserUpdated(
                    tenantUserUuid: $tenantUser->uuid,
                    actorId: Auth::id(),
                    changes: array_keys($changes)
                ));
            }

            return $tenantUser;
        });
    }

    public function delete(TenantUser $tenantUser): void
    {
        DB::transaction(function () use ($tenantUser) {
            $this->assertBelongsToCurrentTenant($tenantUser);

            $this->repository->delete($tenantUser);

            event(new TenantUserDeleted(
                tenantUserUuid: $tenantUser->uuid,
                actorId: Auth::id()
            ));
        });
    }

    private function assertBelongsToCurrentTenant(TenantUser $tenantUser): void
    {
        if ((int) $tenantUser->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
