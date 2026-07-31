<?php

namespace App\Models\Marketplace;

use App\Models\BaseModel;
use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceOrder extends BaseModel
{
    protected $table = 'marketplace_orders';

    protected $fillable = [
        'tenant_id',
        'integration_id',
        'marketplace_merchant_id',
        'internal_order_id',
        'external_id',
        'display_id',
        'order_number',
        'status',
        'customer_name',
        'total_amount',
        'payload',
        'raw_updated_at',
        'last_synced_at',
        'imported_at',
        'import_error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'total_amount' => 'float',
        'raw_updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'imported_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'integration_id',
        'marketplace_merchant_id',
        'internal_order_id',
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

    public function internalOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'internal_order_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(MarketplaceAction::class, 'marketplace_order_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MarketplaceEvent::class, 'external_order_id', 'external_id');
    }
}
