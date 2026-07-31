<?php

namespace App\Repositories\Contracts;

interface StockLocationRepositoryInterface extends BaseRepositoryInterface
{
    public function nameExists(int $tenantId, string $name, ?int $excludeId = null): bool;
}
