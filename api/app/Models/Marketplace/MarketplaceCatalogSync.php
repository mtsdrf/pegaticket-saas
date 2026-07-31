<?php

namespace App\Models\Marketplace;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceCatalogSync extends BaseModel
{
    protected $table = 'marketplace_catalog_syncs';

    protected $fillable = [
        'tenant_id',
        'integration_id',
        'marketplace_merchant_id',
        'status',
        'categories_total',
        'items_total',
        'processed_count',
        'success_count',
        'failed_count',
        'started_at',
        'finished_at',
        'error_message',
        'request_snapshot',
        'response_snapshot',
    ];

    protected $casts = [
        'categories_total' => 'integer',
        'items_total' => 'integer',
        'processed_count' => 'integer',
        'success_count' => 'integer',
        'failed_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'request_snapshot' => 'array',
        'response_snapshot' => 'array',
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

    public function items(): HasMany
    {
        return $this->hasMany(MarketplaceCatalogSyncItem::class, 'marketplace_catalog_sync_id');
    }
}
