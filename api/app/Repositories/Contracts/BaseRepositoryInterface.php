<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Interface BaseRepositoryInterface
 * 
 * Contrato base para todos os repositories.
 * Define operações comuns de CRUD e consultas.
 */
interface BaseRepositoryInterface
{
    /**
     * Buscar todos os registros (sem paginação)
     * 
     * @param array $columns
     * @return Collection
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Buscar registros com paginação
     * 
     * @param int $perPage
     * @param array $columns
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * Buscar por ID
     * 
     * @param int $id
     * @param array $columns
     * @return Model|null
     */
    public function find(int $id, array $columns = ['*']): ?Model;

    /**
     * Buscar por UUID
     * 
     * @param string $uuid
     * @param array $columns
     * @return Model|null
     */
    public function findByUuid(string $uuid, array $columns = ['*']): ?Model;

    /**
     * Buscar por UUID ou falhar
     * 
     * @param string $uuid
     * @param array $columns
     * @return Model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByUuidOrFail(string $uuid, array $columns = ['*']): Model;

    /**
     * Criar novo registro
     * 
     * @param array $data
     * @return Model
     */
    public function create(array $data): Model;

    /**
     * Atualizar registro
     * 
     * @param Model $model
     * @param array $data
     * @return Model
     */
    public function update(Model $model, array $data): Model;

    /**
     * Deletar registro (soft delete)
     * 
     * @param Model $model
     * @return bool
     */
    public function delete(Model $model): bool;

    /**
     * Deletar permanentemente
     * 
     * @param Model $model
     * @return bool
     */
    public function forceDelete(Model $model): bool;

    /**
     * Buscar por campo específico
     * 
     * @param string $field
     * @param mixed $value
     * @param array $columns
     * @return Model|null
     */
    public function findBy(string $field, mixed $value, array $columns = ['*']): ?Model;

    /**
     * Buscar múltiplos por campo
     * 
     * @param string $field
     * @param mixed $value
     * @param array $columns
     * @return Collection
     */
    public function findAllBy(string $field, mixed $value, array $columns = ['*']): Collection;

    /**
     * Contar registros
     * 
     * @return int
     */
    public function count(): int;

    /**
     * Verificar se existe
     * 
     * @param string $field
     * @param mixed $value
     * @return bool
     */
    public function exists(string $field, mixed $value): bool;
}