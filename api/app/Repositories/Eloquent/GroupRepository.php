<?php

namespace App\Repositories\Eloquent;

use App\Models\Functionality\Functionality;
use App\Models\Group\Group;
use App\Models\Permission\Action;
use App\Repositories\Contracts\GroupRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Class GroupRepository
 * 
 * Repository para operações com Group.
 * Gerencia grupos, usuários e permissões.
 */
class GroupRepository extends BaseRepository implements GroupRepositoryInterface
{
    /**
     * GroupRepository constructor.
     * 
     * @param Group $model
     */
    public function __construct(Group $model)
    {
        parent::__construct($model);
    }

    /**
     * {@inheritdoc}
     */
    public function findBySlug(string $slug): ?Group
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('slug', $slug)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveGroups(): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getIdsByUuids(array $uuids): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->whereIn('uuid', $uuids)
            ->pluck('id');
    }

    /**
     * {@inheritdoc}
     */
    public function syncUsers(Group $group, array $userUuids): void
    {
        $userIds = DB::table('users')
            ->whereIn('uuid', $userUuids)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        // Soft delete de todos os vínculos existentes
        DB::table('group_user')
            ->where('group_id', $group->id)
            ->update(['deleted_at' => now()]);

        // Reativar ou criar novos vínculos
        foreach ($userIds as $userId) {
            DB::table('group_user')->updateOrInsert(
                ['group_id' => $group->id, 'user_id' => $userId],
                [
                    'uuid' => (string) Str::uuid(),
                    'deleted_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function syncPermissions(Group $group, array $permissions): void
    {
        $actionIds = Action::pluck('id', 'key');

        // Soft delete de todas as permissões existentes
        DB::table('group_permissions')
            ->where('group_id', $group->id)
            ->update(['deleted_at' => now()]);

        // Criar/reativar permissões
        foreach ($permissions as $permission) {
            $functionalityId = Functionality::where('slug', $permission['functionality_slug'])
                ->value('id');

            if (!$functionalityId) {
                continue; // Skip se funcionalidade não existe
            }

            foreach ($permission['actions'] as $actionKey) {
                if (!isset($actionIds[$actionKey])) {
                    continue; // Skip se action não existe
                }

                DB::table('group_permissions')->updateOrInsert(
                    [
                        'group_id' => $group->id,
                        'functionality_id' => $functionalityId,
                        'action_id' => $actionIds[$actionKey],
                    ],
                    [
                        'uuid' => (string) Str::uuid(),
                        'deleted_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getGroupUsers(Group $group): Collection
    {
        return DB::table('group_user')
            ->join('users', 'users.id', '=', 'group_user.user_id')
            ->where('group_user.group_id', $group->id)
            ->whereNull('group_user.deleted_at')
            ->whereNull('users.deleted_at')
            ->select('users.*')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getGroupPermissions(Group $group): Collection
    {
        return DB::table('group_permissions')
            ->join('functionalities', 'functionalities.id', '=', 'group_permissions.functionality_id')
            ->join('actions', 'actions.id', '=', 'group_permissions.action_id')
            ->where('group_permissions.group_id', $group->id)
            ->whereNull('group_permissions.deleted_at')
            ->select([
                'functionalities.slug as functionality_slug',
                'functionalities.name as functionality_name',
                'actions.key as action_key',
                'actions.name as action_name',
            ])
            ->get();
    }
}