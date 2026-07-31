<?php

namespace App\Models\Pdv;

use App\Models\BaseModel;
use App\Models\Order\Order;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends BaseModel
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $table = 'cash_sessions';

    protected $fillable = [
        'tenant_id',
        'cash_register_id',
        'opened_by',
        'closed_by',
        'opened_at',
        'opening_amount',
        'closed_at',
        'closing_amount_declared',
        'closing_amount_expected',
        'difference',
        'status',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_amount' => 'decimal:2',
        'closing_amount_declared' => 'decimal:2',
        'closing_amount_expected' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'cash_register_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
