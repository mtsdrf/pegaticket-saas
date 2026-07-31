<?php

namespace App\Repositories\Eloquent;

use App\Models\Functionality\Functionality;
use App\Repositories\Contracts\FunctionalityRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Class FunctionalityRepository
 * 
 * Repository para operações com Functionality.
 * Gerencia funcionalidades do sistema.
 */
class FunctionalityRepository extends BaseRepository implements FunctionalityRepositoryInterface
{
    /**
     * FunctionalityRepository constructor.
     * 
     * @param Functionality $model
     */
    public function __construct(Functionality $model)
    {
        parent::__construct($model);
    }

    /**
     * {@inheritdoc}
     */
    public function findBySlug(string $slug): ?Functionality
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('slug', $slug)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveFunctionalities(): Collection
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
    public function getIdBySlug(string $slug): ?int
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('slug', $slug)
            ->value('id');
    }

    /**
     * {@inheritdoc}
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = $this->model
            ->whereNull('deleted_at')
            ->where('slug', $slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}