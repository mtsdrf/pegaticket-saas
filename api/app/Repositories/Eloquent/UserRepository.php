<?php

namespace App\Repositories\Eloquent;

use App\Models\Group\Group;
use App\Models\User\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Class UserRepository
 * 
 * Repository para operações com User.
 * Gerencia queries e persistência de dados de usuários.
 */
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    /**
     * UserRepository constructor.
     * 
     * @param User $model
     */
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * {@inheritdoc}
     */
    public function createUser(array $data): User
    {
        return $this->model->create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function updateUser(User $user, array $data): User
    {
        $user->fill($data);
        $user->save();

        return $user->fresh();
    }

    /**
     * {@inheritdoc}
     */
    public function paginateWithGroups(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->with(['groups'])
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function paginateByTenantWithGroups(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->with(['groups'])
            ->whereNull('users.deleted_at')
            ->whereExists(function ($query) use ($tenantId) {
                $query->selectRaw('1')
                    ->from('tenant_users')
                    ->whereColumn('tenant_users.user_id', 'users.id')
                    ->where('tenant_users.tenant_id', $tenantId)
                    ->where('tenant_users.is_active', true)
                    ->whereNull('tenant_users.deleted_at');
            })
            ->orderByDesc('users.id')
            ->paginate($perPage);
    }

    public function userBelongsToTenant(User $user, int $tenantId): bool
    {
        return DB::table('tenant_users')
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * {@inheritdoc}
     */
    public function findByEmail(string $email): ?User
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('email', $email)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveUsers(): Collection
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
    public function getUsersByGroupId(int $groupId): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->whereHas('groups', function ($query) use ($groupId) {
                $query->where('groups.id', $groupId);
            })
            ->with('groups')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function syncGroups(User $user, array $groupUuids): User
    {
        if (empty($groupUuids)) {
            return $user;
        }

        $groupIds = Group::whereIn('uuid', $groupUuids)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        foreach ($groupIds as $groupId) {
            // Verificar se já existe
            $existing = DB::table('group_user')
                ->where('group_id', $groupId)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                // Atualizar registro existente (reativar se soft deleted)
                DB::table('group_user')
                    ->where('group_id', $groupId)
                    ->where('user_id', $user->id)
                    ->update([
                        'deleted_at' => null,
                        'deleted_by' => null,
                        'updated_at' => now(),
                    ]);
            } else {
                // Criar novo registro
                DB::table('group_user')->insert([
                    'uuid' => (string) Str::uuid(),
                    'group_id' => $groupId,
                    'user_id' => $user->id,
                    'deleted_at' => null,
                    'deleted_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $user->fresh('groups');
    }
}
