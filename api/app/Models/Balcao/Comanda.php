<?php

namespace App\Models\Balcao;

use App\Models\BaseModel;
use App\Models\Order\Order;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comanda extends BaseModel
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSING = 'closing';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'comandas';

    protected $fillable = [
        'tenant_id',
        'table_id',
        'label',
        'status',
        'opened_by',
        'opened_at',
        'closed_at',
        'service_fee_percent',
        'order_id',
        'client_comanda_uuid',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'service_fee_percent' => 'decimal:2',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'table_id',
        'order_id',
        'client_comanda_uuid',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ComandaItem::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
