<?php

namespace App\Repositories\Contracts;

use App\Models\Group\Group;
use Illuminate\Support\Collection;

/**
 * Interface GroupRepositoryInterface
 * 
 * Contrato específico para operações com Group.
 */
interface GroupRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Buscar grupo por slug
     * 
     * @param string $slug
     * @return Group|null
     */
    public function findBySlug(string $slug): ?Group;

    /**
     * Buscar grupos ativos
     * 
     * @return Collection
     */
    public function getActiveGroups(): Collection;

    /**
     * Buscar IDs de grupos por UUIDs
     * 
     * @param array $uuids
     * @return Collection
     */
    public function getIdsByUuids(array $uuids): Collection;

    /**
     * Sincronizar usuários do grupo (soft delete)
     * 
     * @param Group $group
     * @param array $userUuids
     * @return void
     */
    public function syncUsers(Group $group, array $userUuids): void;

    /**
     * Sincronizar permissões do grupo (soft delete)
     * 
     * @param Group $group
     * @param array $permissions
     * @return void
     */
    public function syncPermissions(Group $group, array $permissions): void;

    /**
     * Buscar usuários do grupo
     * 
     * @param Group $group
     * @return Collection
     */
    public function getGroupUsers(Group $group): Collection;

    /**
     * Buscar permissões do grupo
     * 
     * @param Group $group
     * @return Collection
     */
    public function getGroupPermissions(Group $group): Collection;
}