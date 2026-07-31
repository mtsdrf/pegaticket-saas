<?php

namespace App\Models\Marketplace;

use App\Models\BaseModel;
use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceCatalogSyncItem extends BaseModel
{
    protected $table = 'marketplace_catalog_sync_items';

    protected $fillable = [
        'tenant_id',
        'marketplace_catalog_sync_id',
        'product_id',
        'entity_type',
        'entity_key',
        'external_entity_id',
        'batch_id',
        'status',
        'request_payload',
        'response_payload',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'processed_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'marketplace_catalog_sync_id',
        'product_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function sync(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCatalogSync::class, 'marketplace_catalog_sync_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
