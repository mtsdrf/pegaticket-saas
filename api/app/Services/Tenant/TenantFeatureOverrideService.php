<?php

namespace App\Services\Tenant;

use App\DTOs\Tenant\SyncTenantFeatureOverridesDTO;
use App\Events\Tenant\TenantFeatureOverridesSynced;
use App\Models\Tenant\Tenant;
use App\Repositories\Contracts\TenantFeatureOverrideRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Feature flag por tenant individual (roadmap A5, item 19) — CRUD-sync
 * de tenants/{tenant}/feature-overrides, exclusivo de admin da plataforma
 * (reaproveita perm:tenants,read/update, sem Functionality/Action nova).
 * A leitura de acesso REAL (gate do middleware perm:) é resolvida em
 * PermissionService::resolveTenantAllowedFunctionalities(), não aqui —
 * este Service só gerencia o CRUD dos overrides.
 */
class TenantFeatureOverrideService
{
    public function __construct(
        private TenantFeatureOverrideRepositoryInterface $repository
    ) {
    }

    public function getOverrides(Tenant $tenant): Collection
    {
        return $this->repository->getForTenant($tenant->id);
    }

    public function syncOverrides(Tenant $tenant, SyncTenantFeatureOverridesDTO $dto): void
    {
        DB::transaction(function () use ($tenant, $dto) {
            $this->repository->syncForTenant($tenant->id, $dto->overrides);

            event(new TenantFeatureOverridesSynced(
                tenantId: $tenant->id,
                actorId: Auth::id()
            ));
        });
    }
}
