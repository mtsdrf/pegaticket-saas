<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface EventCategoryRepositoryInterface extends BaseRepositoryInterface
{
    public function nameExists(int $tenantId, string $name, ?int $excludeId = null): bool;
    public function getActiveCategories(int $tenantId): Collection;
}
