<?php

namespace App\Models\Marketplace;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceMerchant extends BaseModel
{
    protected $table = 'marketplace_merchants';

    protected $fillable = [
        'tenant_id',
        'integration_id',
        'external_id',
        'name',
        'is_active',
        'status_payload',
        'metadata',
        'last_seen_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'status_payload' => 'array',
        'metadata' => 'array',
        'last_seen_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'integration_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(MarketplaceIntegration::class, 'integration_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MarketplaceEvent::class, 'marketplace_merchant_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MarketplaceOrder::class, 'marketplace_merchant_id');
    }

    public function catalogMappings(): HasMany
    {
        return $this->hasMany(MarketplaceCatalogMapping::class, 'marketplace_merchant_id');
    }
}
