<?php

namespace App\Models\Marketplace;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceAction extends BaseModel
{
    protected $table = 'marketplace_actions';

    protected $fillable = [
        'tenant_id',
        'integration_id',
        'marketplace_order_id',
        'action',
        'status',
        'request_payload',
        'response_payload',
        'executed_at',
        'error_message',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'executed_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'integration_id',
        'marketplace_order_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(MarketplaceIntegration::class, 'integration_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'marketplace_order_id');
    }
}
