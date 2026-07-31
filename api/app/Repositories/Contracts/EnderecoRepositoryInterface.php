<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface EnderecoRepositoryInterface extends BaseRepositoryInterface
{
    public function getActiveEnderecos(int $tenantId): Collection;
}
