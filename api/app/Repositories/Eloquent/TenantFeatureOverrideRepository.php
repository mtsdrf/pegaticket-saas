<?php

namespace App\Repositories\Eloquent;

use App\Models\Tenant\TenantFeatureOverride;
use App\Repositories\Contracts\TenantFeatureOverrideRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TenantFeatureOverrideRepository extends BaseRepository implements TenantFeatureOverrideRepositoryInterface
{
    public function __construct(TenantFeatureOverride $model)
    {
        parent::__construct($model);
    }

    public function getForTenant(int $tenantId): Collection
    {
        return DB::table('tenant_feature_overrides as tfo')
            ->join('functionalities as f', 'f.id', '=', 'tfo.functionality_id')
            ->where('tfo.tenant_id', $tenantId)
            ->whereNull('tfo.deleted_at')
            ->whereNull('f.deleted_at')
            ->orderBy('f.slug')
            ->select('f.slug as functionality', 'tfo.is_enabled as is_enabled')
            ->get()
            ->map(fn ($row) => (object) [
                'functionality' => $row->functionality,
                'is_enabled' => (bool) $row->is_enabled,
            ]);
    }

    public function syncForTenant(int $tenantId, array $overrides): void
    {
        DB::table('tenant_feature_overrides')
            ->where('tenant_id', $tenantId)
            ->delete();

        $functionalityIds = DB::table('functionalities')
            ->whereIn('slug', array_column($overrides, 'functionality'))
            ->whereNull('deleted_at')
            ->pluck('id', 'slug');

        foreach ($overrides as $override) {
            $functionalityId = $functionalityIds[$override['functionality']] ?? null;

            if (!$functionalityId) {
                continue;
            }

            TenantFeatureOverride::create([
                'tenant_id' => $tenantId,
                'functionality_id' => $functionalityId,
                'is_enabled' => $override['is_enabled'],
            ]);
        }
    }
}
