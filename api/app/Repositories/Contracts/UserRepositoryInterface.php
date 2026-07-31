<?php

namespace App\Repositories\Contracts;

use App\Models\User\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Interface UserRepositoryInterface
 * 
 * Contrato específico para operações com User.
 * Métodos adicionais além do BaseRepository.
 */
interface UserRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Criar novo usuário
     * 
     * @param array $data
     * @return User
     */
    public function createUser(array $data): User;

    /**
     * Atualizar usuário
     * 
     * @param User $user
     * @param array $data
     * @return User
     */
    public function updateUser(User $user, array $data): User;

    /**
     * Buscar usuários com seus grupos carregados
     * 
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function paginateWithGroups(int $perPage = 15): LengthAwarePaginator;

    /**
     * Buscar usuário por email
     * 
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User;

    /**
     * Buscar usuários ativos
     * 
     * @return Collection
     */
    public function getActiveUsers(): Collection;

    /**
     * Buscar usuários de um grupo específico
     * 
     * @param int $groupId
     * @return Collection
     */
    public function getUsersByGroupId(int $groupId): Collection;

    /**
     * Atualizar grupos do usuário
     * 
     * @param User $user
     * @param array $groupUuids
     * @return User
     */
    public function syncGroups(User $user, array $groupUuids): User;
}