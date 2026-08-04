<?php

namespace App\Models\Finance;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Settlement extends BaseModel
{
    protected $table = 'settlements';

    protected $fillable = [
        'tenant_id',
        'code',
        'status',
        'scheduled_for',
        'released_at',
        'gross_amount',
        'platform_fee_amount',
        'processor_fee_amount',
        'net_amount',
        'metadata',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'released_at' => 'datetime',
        'gross_amount' => 'decimal:2',
        'platform_fee_amount' => 'decimal:2',
        'processor_fee_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(SettlementAdjustment::class);
    }
}
