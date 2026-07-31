<?php

namespace App\Services\User;

use App\DTOs\User\CreateUserDTO;
use App\DTOs\User\UpdateUserDTO;
use App\Events\User\{UserCreated, UserUpdated, UserDeleted};
use App\Models\User\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Group\Group;
use App\Services\Permission\PermissionService;
use App\Support\GridQuery;

/**
 * Class UserService
 * 
 * Camada de serviço para User.
 * Orquestra regras de negócio e delega persistência ao Repository.
 */
class UserService
{
    /**
     * @var UserRepositoryInterface
     */
    protected UserRepositoryInterface $repository;

    /**
     * UserService constructor.
     * 
     * @param UserRepositoryInterface $repository
     */
    public function __construct(
        UserRepositoryInterface $repository,
        private PermissionService $permissionService
    )
    {
        $this->repository = $repository;
    }

    /**
     * Listar usuários paginados com seus grupos
     * 
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function paginate(
        int $perPage = 15,
        array $filters = [],
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator
    {
        $sortable = [
            'name' => 'users.name',
            'email' => 'users.email',
            'is_active' => 'users.is_active',
        ];

        $query = User::query()
            ->select(['users.id', 'users.uuid', 'users.name', 'users.email', 'users.is_active', 'users.created_at', 'users.updated_at'])
            ->with(['groups'])
            ->whereNull('users.deleted_at');

        if ($this->shouldScopeUsersToTenant()) {
            $tenantId = (int) app('tenant_id');

            $query->whereExists(function ($sub) use ($tenantId) {
                $sub->selectRaw('1')
                    ->from('tenant_users')
                    ->whereColumn('tenant_users.user_id', 'users.id')
                    ->where('tenant_users.tenant_id', $tenantId)
                    ->where('tenant_users.is_active', true)
                    ->whereNull('tenant_users.deleted_at');
            });
        }

        GridQuery::applyTextFilters($query, $filters, [
            'name' => 'users.name',
            'email' => 'users.email',
        ]);

        GridQuery::applyBooleanFilters($query, $filters, [
            'is_active' => 'users.is_active',
        ]);

        $sortColumn = is_string($sortBy) ? ($sortable[$sortBy] ?? null) : null;
        $query->orderBy($sortColumn ?? 'users.name', GridQuery::normalizeSortDir($sortDir));

        return $query->paginate($perPage);
    }

    /**
     * Criar novo usuário
     * 
     * @param CreateUserDTO $dto
     * @return User
     */
    public function create(CreateUserDTO $dto): User
    {
        return DB::transaction(function () use ($dto) {
            $groupUuids = $dto->groupUuids;

            if ($this->shouldScopeUsersToTenant()) {
                $clientsGroupUuid = Group::where('slug', 'clients')->whereNull('deleted_at')->value('uuid');
                $groupUuids = $clientsGroupUuid ? [$clientsGroupUuid] : [];
            }

            // Criar usuário
            $user = $this->repository->createUser([
                'name' => $dto->name,
                'email' => $dto->email,
                'password' => Hash::make($dto->password),
                'is_active' => $dto->isActive,
            ]);

            // Sincronizar grupos (se fornecidos)
            if (!empty($groupUuids)) {
                $user = $this->repository->syncGroups($user, $groupUuids);
            }

            // Disparar evento
            event(new UserCreated(
                userUuid: $user->uuid,
                actorId: Auth::id()
            ));

            return $user;
        });
    }

    /**
     * Atualizar usuário
     * 
     * @param User $user
     * @param UpdateUserDTO $dto
     * @return User
     */
    public function update(User $user, UpdateUserDTO $dto): User
    {
        return DB::transaction(function () use ($user, $dto) {
            $original = $user->getOriginal();

            // Preparar dados para atualização
            $data = array_filter([
                'name' => $dto->name,
                'email' => $dto->email,
                'is_active' => $dto->isActive,
            ], fn($v) => !is_null($v));

            // Hash da senha se fornecida
            if ($dto->password !== null) {
                $data['password'] = Hash::make($dto->password);
            }

            // Atualizar apenas se houver dados
            if (!empty($data)) {
                $user = $this->repository->updateUser($user, $data);

                $changes = array_diff_assoc($user->getAttributes(), $original);

                // Disparar evento
                event(new UserUpdated(
                    userUuid: $user->uuid,
                    actorId: Auth::id(),
                    changes: array_keys($changes)
                ));
            }

            return $user->load('groups');
        });
    }

    /**
     * Deletar usuário (soft delete)
     * 
     * @param User $user
     * @return void
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->repository->delete($user);

            // Disparar evento
            event(new UserDeleted(
                userUuid: $user->uuid,
                actorId: Auth::id()
            ));
        });
    }

    public function ensureUserAccessible(User $user): void
    {
        if (!$this->shouldScopeUsersToTenant()) {
            return;
        }

        if (!$this->repository->userBelongsToTenant($user, (int) app('tenant_id'))) {
            abort(404);
        }
    }

    private function shouldScopeUsersToTenant(): bool
    {
        if (!auth()->check() || !app()->bound('tenant_id')) {
            return false;
        }

        return !$this->permissionService->userHasGroupSlug((int) auth()->id(), 'administrators');
    }
}
