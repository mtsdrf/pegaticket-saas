<?php

namespace App\Repositories\Contracts;

interface BairroRepositoryInterface extends BaseRepositoryInterface
{
    public function nameExists(int $cidadeId, string $name, ?int $excludeId = null): bool;
}
