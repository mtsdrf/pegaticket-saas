<?php

namespace App\Repositories\Eloquent;

use App\Models\Location\Endereco;
use App\Repositories\Contracts\EnderecoRepositoryInterface;
use Illuminate\Support\Collection;

class EnderecoRepository extends BaseRepository implements EnderecoRepositoryInterface
{
    public function __construct(Endereco $model)
    {
        parent::__construct($model);
    }

    public function getActiveEnderecos(int $tenantId): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('logradouro')
            ->get();
    }
}
