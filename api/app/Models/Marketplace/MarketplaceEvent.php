<?php

namespace App\Models\Marketplace;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceEvent extends BaseModel
{
    protected $table = 'marketplace_events';

    protected $fillable = [
        'tenant_id',
        'integration_id',
        'marketplace_merchant_id',
        'external_event_id',
        'external_order_id',
        'event_type',
        'event_full_code',
        'payload',
        'status',
        'processing_attempts',
        'occurred_at',
        'acknowledged_at',
        'processed_at',
        'last_attempted_at',
        'dead_lettered_at',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'processed_at' => 'datetime',
        'last_attempted_at' => 'datetime',
        'dead_lettered_at' => 'datetime',
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
