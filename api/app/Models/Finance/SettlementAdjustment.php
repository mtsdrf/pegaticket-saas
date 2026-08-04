<?php

namespace App\Models\Finance;

use App\Models\BaseModel;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleRefund;
use App\Models\Subscription\Refund;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementAdjustment extends BaseModel
{
    protected $table = 'settlement_adjustments';

    protected $fillable = [
        'tenant_id',
        'settlement_id',
        'receivable_id',
        'sale_id',
        'sale_refund_id',
        'refund_id',
        'type',
        'amount',
        'reason',
        'status',
        'resolution_type',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'settlement_id',
        'receivable_id',
        'sale_id',
        'sale_refund_id',
        'refund_id',
        'resolved_by',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleRefund(): BelongsTo
    {
        return $this->belongsTo(SaleRefund::class);
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }
}
