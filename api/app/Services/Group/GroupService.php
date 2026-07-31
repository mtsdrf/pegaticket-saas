<?php

namespace App\Services\Group;

use App\DTOs\Group\{CreateGroupDTO, UpdateGroupDTO, SyncGroupUsersDTO, SyncGroupPermissionsDTO};
use App\Events\Group\{
    GroupCreated,
    GroupUpdated,
    GroupDeleted,
    GroupUsersSynced,
    GroupPermissionsSynced
};
use App\Models\Group\Group;
use App\Repositories\Contracts\GroupRepositoryInterface;
use App\Support\GridQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Class GroupService
 * 
 * Camada de serviço para Group.
 * Orquestra regras de negócio complexas de grupos e permissões.
 */
class GroupService
{
    /**
     * @var GroupRepositoryInterface
     */
    protected GroupRepositoryInterface $repository;

    /**
     * GroupService constructor.
     * 
     * @param GroupRepositoryInterface $repository
     */
    public function __construct(GroupRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Listar grupos paginados
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
            'name' => 'groups.name',
            'slug' => 'groups.slug',
            'is_active' => 'groups.is_active',
        ];

        $query = Group::query()
            ->select(['groups.id', 'groups.uuid', 'groups.name', 'groups.slug', 'groups.is_active', 'groups.created_at', 'groups.updated_at'])
            ->whereNull('groups.deleted_at');

        GridQuery::applyTextFilters($query, $filters, [
            'name' => 'groups.name',
            'slug' => 'groups.slug',
        ]);

        GridQuery::applyBooleanFilters($query, $filters, [
            'is_active' => 'groups.is_active',
        ]);

        $sortColumn = is_string($sortBy) ? ($sortable[$sortBy] ?? null) : null;
        $query->orderBy($sortColumn ?? 'groups.name', GridQuery::normalizeSortDir($sortDir));

        return $query->paginate($perPage);
    }

    /**
     * Criar novo grupo
     * 
     * @param CreateGroupDTO $dto
     * @return Group
     */
    public function create(CreateGroupDTO $dto): Group
    {
        return DB::transaction(function () use ($dto) {
            $group = $this->repository->create([
                'name' => $dto->name,
                'slug' => $dto->slug,
                'is_active' => $dto->isActive,
            ]);

            event(new GroupCreated(
                groupUuid: $group->uuid,
                actorId: Auth::id()
            ));

            return $group;
        });
    }

    /**
     * Atualizar grupo
     * 
     * @param Group $group
     * @param UpdateGroupDTO $dto
     * @return Group
     */
    public function update(Group $group, UpdateGroupDTO $dto): Group
    {
        return DB::transaction(function () use ($group, $dto) {
            $original = $group->getOriginal();

            $data = array_filter([
                'name' => $dto->name,
                'slug' => $dto->slug,
                'is_active' => $dto->isActive,
            ], fn($v) => !is_null($v));

            if (!empty($data)) {
                $group = $this->repository->update($group, $data);

                $changes = array_diff_assoc($group->getAttributes(), $original);

                event(new GroupUpdated(
                    groupUuid: $group->uuid,
                    actorId: Auth::id(),
                    changes: array_keys($changes)
                ));
            }

            return $group;
        });
    }

    /**
     * Deletar grupo (soft delete)
     * 
     * @param Group $group
     * @return void
     */
    public function delete(Group $group): void
    {
        DB::transaction(function () use ($group) {
            $this->repository->delete($group);

            event(new GroupDeleted(
                groupUuid: $group->uuid,
                actorId: Auth::id()
            ));
        });
    }

    /**
     * Sincronizar usuários do grupo (soft delete)
     * 
     * @param Group $group
     * @param SyncGroupUsersDTO $dto
     * @return Group
     */
    public function syncUsersSoft(Group $group, SyncGroupUsersDTO $dto): Group
    {
        return DB::transaction(function () use ($group, $dto) {
            $this->repository->syncUsers($group, $dto->userUuids);

            event(new GroupUsersSynced(
                groupUuid: $group->uuid,
                userUuids: $dto->userUuids,
                actorId: Auth::id()
            ));

            return $group;
        });
    }

    /**
     * Sincronizar permissões do grupo (soft delete)
     * 
     * @param Group $group
     * @param SyncGroupPermissionsDTO $dto
     * @return void
     */
    public function syncPermissionsSoft(Group $group, SyncGroupPermissionsDTO $dto): void
    {
        DB::transaction(function () use ($group, $dto) {
            $this->repository->syncPermissions($group, $dto->permissions);

            event(new GroupPermissionsSynced(
                groupUuid: $group->uuid,
                permissions: $dto->permissions,
                actorId: Auth::id()
            ));
        });
    }
}
