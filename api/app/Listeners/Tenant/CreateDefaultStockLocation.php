<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\TenantCreated;
use App\Models\Tenant\Tenant;
use App\Services\Stock\StockLocationService;

/**
 * Garante que todo tenant novo já nasce com uma StockLocation padrão
 * ("Depósito Principal") — usuário não é obrigado a configurar local
 * antes de conseguir movimentar estoque. Idempotente via
 * StockLocationService::ensureDefaultLocation().
 */
class CreateDefaultStockLocation
{
    public function __construct(
        private StockLocationService $service
    ) {
    }

    public function handle(TenantCreated $event): void
    {
        $tenant = Tenant::where('uuid', $event->tenantUuid)->first();

        if (!$tenant) {
            return;
        }

        $this->service->ensureDefaultLocation($tenant->id);
    }
}
