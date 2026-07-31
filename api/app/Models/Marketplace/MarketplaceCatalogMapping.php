<?php

namespace App\Models\Marketplace;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceCatalogMapping extends BaseModel
{
    protected $table = 'marketplace_catalog_mappings';

    protected $fillable = [
        'tenant_id',
        'integration_id',
        'marketplace_merchant_id',
        'entity_type',
        'entity_key',
        'internal_uuid',
        'external_entity_id',
        'metadata',
        'last_synced_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'integration_id',
        'marketplace_merchant_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(MarketplaceIntegration::class, 'integration_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(MarketplaceMerchant::class, 'marketplace_merchant_id');
    }
}
