<?php

namespace App\Models\Inventory;

use App\Models\BaseModel;
use App\Models\Event\Event;
use App\Models\Event\EventSession;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Order\Order;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryHold extends BaseModel
{
    public const STATUS_RESERVED = 'reservado';
    public const STATUS_EXPIRED = 'expirado';
    public const STATUS_ABANDONED = 'abandonado';
    public const STATUS_CONVERTED = 'convertido_em_pedido';

    protected $table = 'inventory_holds';

    protected $fillable = [
        'tenant_id',
        'final_customer_id',
        'event_id',
        'event_session_id',
        'converted_order_id',
        'session_token',
        'status',
        'origin',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'final_customer_id',
        'event_id',
        'event_session_id',
        'converted_order_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function finalCustomer(): BelongsTo
    {
        return $this->belongsTo(FinalCustomer::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryHoldItem::class);
    }
}
