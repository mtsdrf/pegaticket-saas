<?php

namespace App\Repositories\Eloquent;

use App\Models\Tenant\TenantSettings;
use App\Repositories\Contracts\TenantSettingsRepositoryInterface;
use Illuminate\Database\QueryException;

class TenantSettingsRepository extends BaseRepository implements TenantSettingsRepositoryInterface
{
    public function __construct(TenantSettings $model)
    {
        parent::__construct($model);
    }

    public function findOrCreateForTenant(int $tenantId): TenantSettings
    {
        $settings = $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->first();

        if ($settings) {
            return $settings;
        }

        try {
            return $this->model->create([
                'tenant_id' => $tenantId,
                'send_tracking_link_whatsapp' => false,
                'storefront_enabled' => true,
            ]);
        } catch (QueryException $e) {
            // Corrida entre duas primeiras leituras concorrentes — a
            // unique (uniq_tenant_settings_tenant) já garantiu 1 linha,
            // só falta buscar a que o outro request criou.
            return $this->model
                ->whereNull('deleted_at')
                ->where('tenant_id', $tenantId)
                ->firstOrFail();
        }
    }
}
