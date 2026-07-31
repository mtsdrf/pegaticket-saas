<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface TenantFeatureOverrideRepositoryInterface
{
    /**
     * @return Collection<int, object{functionality: string, is_enabled: bool}>
     */
    public function getForTenant(int $tenantId): Collection;

    /**
     * Substituição completa dos overrides do tenant (mesmo padrão de
     * PlanFunctionalityRepository::syncFunctionalities: delete + recreate).
     *
     * @param array<int, array{functionality: string, is_enabled: bool}> $overrides
     */
    public function syncForTenant(int $tenantId, array $overrides): void;
}
