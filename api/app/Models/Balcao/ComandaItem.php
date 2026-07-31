<?php

namespace App\Models\Balcao;

use App\Models\BaseModel;
use App\Models\Product\Product;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComandaItem extends BaseModel
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT_TO_STATION = 'sent_to_station';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY = 'ready';
    public const STATUS_DELIVERED_TO_TABLE = 'delivered_to_table';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Estados terminais: nenhuma transição parte deles.
     */
    public const TERMINAL_STATUSES = [
        self::STATUS_DELIVERED_TO_TABLE,
        self::STATUS_CANCELLED,
    ];

    /**
     * Fila viva de uma estação (tickets do KDS) — itens já enviados e ainda
     * não entregues/cancelados.
     */
    public const ACTIVE_STATION_STATUSES = [
        self::STATUS_SENT_TO_STATION,
        self::STATUS_PREPARING,
        self::STATUS_READY,
    ];

    protected $table = 'comanda_items';

    protected $fillable = [
        'tenant_id',
        'comanda_id',
        'product_id',
        'station_id',
        'qty',
        'unit_price',
        'notes',
        'prep_status',
        'sent_to_station_at',
        'preparing_at',
        'ready_at',
        'delivered_at',
        'cancelled_at',
        'added_by',
        'cancelled_reason',
        'client_item_uuid',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'sent_to_station_at' => 'datetime',
        'preparing_at' => 'datetime',
        'ready_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'comanda_id',
        'product_id',
        'station_id',
        'client_item_uuid',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function isCancelled(): bool
    {
        return $this->prep_status === self::STATUS_CANCELLED;
    }
}
