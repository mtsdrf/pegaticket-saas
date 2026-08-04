<?php

namespace App\Models\Finance;

use App\Models\BaseModel;
use App\Models\Sale\Sale;
use App\Models\Subscription\Payment;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends BaseModel
{
    protected $table = 'ledger_entries';

    protected $fillable = [
        'tenant_id',
        'sale_id',
        'payment_id',
        'receivable_id',
        'settlement_id',
        'settlement_adjustment_id',
        'direction',
        'entry_type',
        'amount',
        'occurred_at',
        'description',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'sale_id',
        'payment_id',
        'receivable_id',
        'settlement_id',
        'settlement_adjustment_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(SettlementAdjustment::class, 'settlement_adjustment_id');
    }
}
