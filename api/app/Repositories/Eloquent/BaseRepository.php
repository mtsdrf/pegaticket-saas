<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Class BaseRepository
 * 
 * Implementação base para todos os repositories.
 * Contém lógica comum de CRUD.
 */
abstract class BaseRepository implements BaseRepositoryInterface
{
    /**
     * @var Model
     */
    protected Model $model;

    /**
     * BaseRepository constructor.
     * 
     * @param Model $model
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function all(array $columns = ['*']): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->get($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->paginate($perPage, $columns);
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id, array $columns = ['*']): ?Model
    {
        return $this->model
            ->whereNull('deleted_at')
            ->find($id, $columns);
    }

    /**
     * {@inheritdoc}
     */
    public function findByUuid(string $uuid, array $columns = ['*']): ?Model
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('uuid', $uuid)
            ->first($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function findByUuidOrFail(string $uuid, array $columns = ['*']): Model
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('uuid', $uuid)
            ->firstOrFail($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(Model $model, array $data): Model
    {
        $model->fill($data);
        $model->save();

        return $model->fresh();
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Model $model): bool
    {
        return $model->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function forceDelete(Model $model): bool
    {
        return $model->forceDelete();
    }

    /**
     * {@inheritdoc}
     */
    public function findBy(string $field, mixed $value, array $columns = ['*']): ?Model
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where($field, $value)
            ->first($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function findAllBy(string $field, mixed $value, array $columns = ['*']): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where($field, $value)
            ->get($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return $this->model
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $field, mixed $value): bool
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where($field, $value)
            ->exists();
    }

    /**
     * Criar nova instância do model
     * 
     * @return Model
     */
    protected function makeModel(): Model
    {
        return clone $this->model;
    }
}