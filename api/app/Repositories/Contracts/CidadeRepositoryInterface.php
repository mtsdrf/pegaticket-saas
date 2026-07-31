<?php

namespace App\Repositories\Contracts;

interface CidadeRepositoryInterface extends BaseRepositoryInterface
{
    public function nameExists(int $estadoId, string $name, ?int $excludeId = null): bool;
}
