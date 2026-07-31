<?php

namespace App\Repositories\Contracts;

use App\Models\Plan\Plan;
use Illuminate\Support\Collection;

interface PlanRepositoryInterface extends BaseRepositoryInterface
{
    public function findBySlug(string $slug): ?Plan;
    public function getActivePlans(): Collection;
    public function getIdBySlug(string $slug): ?int;
    public function slugExists(string $slug, ?int $excludeId = null): bool;
}
