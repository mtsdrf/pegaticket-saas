<?php

namespace App\Repositories\Eloquent;

use App\Models\Location\Bairro;
use App\Repositories\Contracts\BairroRepositoryInterface;

class BairroRepository extends BaseRepository implements BairroRepositoryInterface
{
    public function __construct(Bairro $model)
    {
        parent::__construct($model);
    }

    public function nameExists(int $cidadeId, string $name, ?int $excludeId = null): bool
    {
        $query = $this->model
            ->whereNull('deleted_at')
            ->where('cidade_id', $cidadeId)
            ->where('name', $name);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
