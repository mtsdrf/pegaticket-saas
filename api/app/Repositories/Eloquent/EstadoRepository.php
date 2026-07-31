<?php

namespace App\Repositories\Eloquent;

use App\Models\Location\Estado;
use App\Repositories\Contracts\EstadoRepositoryInterface;

class EstadoRepository extends BaseRepository implements EstadoRepositoryInterface
{
    public function __construct(Estado $model)
    {
        parent::__construct($model);
    }

    public function ufExists(string $uf, ?int $excludeId = null): bool
    {
        $query = $this->model
            ->whereNull('deleted_at')
            ->where('uf', $uf);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
