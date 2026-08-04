<?php

namespace App\Models\Finance;

use App\Models\BaseModel;
use App\Models\Event\Event;
use App\Models\Sale\Sale;
use App\Models\Subscription\Payment;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receivable extends BaseModel
{
    protected $table = 'receivables';

    protected $fillable = [
        'tenant_id',
        'sale_id',
        'payment_id',
        'event_id',
        'settlement_id',
        'status',
        'currency',
        'gross_amount',
        'platform_fee_amount',
        'processor_fee_amount',
        'net_amount',
        'reserve_amount',
        'reserve_status',
        'reserve_release_at',
        'reserve_released_at',
        'settlement_reference',
        'event_ends_at',
        'available_at',
        'provider',
        'provider_charge_id',
        'provider_split_id',
        'metadata',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'platform_fee_amount' => 'decimal:2',
        'processor_fee_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'reserve_amount' => 'decimal:2',
        'reserve_release_at' => 'datetime',
        'reserve_released_at' => 'datetime',
        'event_ends_at' => 'datetime',
        'available_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'sale_id',
        'payment_id',
        'event_id',
        'settlement_id',
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(SettlementAdjustment::class);
    }
}
