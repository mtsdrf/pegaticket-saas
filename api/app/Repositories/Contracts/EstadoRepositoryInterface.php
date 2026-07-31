<?php

namespace App\Repositories\Contracts;

interface EstadoRepositoryInterface extends BaseRepositoryInterface
{
    public function ufExists(string $uf, ?int $excludeId = null): bool;
}
