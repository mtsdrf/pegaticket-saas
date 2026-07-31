<?php

namespace App\Console\Commands;

use App\Models\Tenant\Tenant;
use App\Services\Permission\PermissionService;
use App\Services\Tenant\TenantProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mantém o papel "Owner" alinhado ao plano ativo do tenant.
 * Útil tanto após novos seeders de funcionalidades/actions quanto após
 * alterações de composição de planos já em produção.
 */
class SyncTenantPermissions extends Command
{
    protected $signature = 'tenants:sync-permissions {--tenant= : uuid de um tenant específico, opcional}';

    protected $description = 'Sincroniza o role Owner com as funcionalidades permitidas no plano do tenant.';

    public function __construct(
        private TenantProvisioningService $provisioningService,
        private PermissionService $permissionService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantUuid = $this->option('tenant');

        $tenants = Tenant::where('is_active', true)
            ->when($tenantUuid, fn($q) => $q->where('uuid', $tenantUuid))
            ->get();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant ativo encontrado.');

            return self::SUCCESS;
        }

        $totalSynced = 0;

        foreach ($tenants as $tenant) {
            $ownerRole = $this->provisioningService->createOwnerRole($tenant);

            if (!$ownerRole) {
                $this->warn("Tenant {$tenant->uuid} não tem role 'owner' — pulado.");

                continue;
            }

            $beforeCount = DB::table('tenant_role_permissions')
                ->where('tenant_role_id', $ownerRole->id)
                ->count();

            $this->provisioningService->syncOwnerRolePermissions($tenant, $ownerRole);
            $this->permissionService->invalidateTenantRole($ownerRole->id);

            $afterCount = DB::table('tenant_role_permissions')
                ->where('tenant_role_id', $ownerRole->id)
                ->count();

            $this->info("Tenant {$tenant->uuid}: Owner sincronizado com {$afterCount} permissão(ões) ativas (delta " . ($afterCount - $beforeCount) . ").");
            $totalSynced++;
        }

        $this->info("Concluído. {$totalSynced} tenant(s) sincronizado(s).");

        return self::SUCCESS;
    }
}
