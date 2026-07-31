<?php

namespace App\Repositories\Eloquent;

use App\Models\Location\Cidade;
use App\Repositories\Contracts\CidadeRepositoryInterface;

class CidadeRepository extends BaseRepository implements CidadeRepositoryInterface
{
    public function __construct(Cidade $model)
    {
        parent::__construct($model);
    }

    public function nameExists(int $estadoId, string $name, ?int $excludeId = null): bool
    {
        $query = $this->model
            ->whereNull('deleted_at')
            ->where('estado_id', $estadoId)
            ->where('name', $name);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
