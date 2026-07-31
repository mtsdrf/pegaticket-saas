<?php

namespace App\Models\Balcao;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableReservation extends BaseModel
{
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_SEATED = 'seated';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    public const ACTIVE_STATUSES = [
        self::STATUS_CONFIRMED,
    ];

    public const TERMINAL_STATUSES = [
        self::STATUS_SEATED,
        self::STATUS_CANCELLED,
        self::STATUS_NO_SHOW,
    ];

    public const SOURCE_INTERNAL = 'internal';
    public const SOURCE_ONLINE = 'online';

    protected $table = 'table_reservations';

    protected $fillable = [
        'tenant_id',
        'table_id',
        'seated_comanda_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'party_size',
        'scheduled_for',
        'duration_minutes',
        'status',
        'source',
        'notes',
        'cancelled_reason',
        'confirmed_at',
        'confirmed_by',
        'seated_at',
        'seated_by',
        'cancelled_at',
        'cancelled_by',
        'no_show_at',
        'no_show_by',
    ];

    protected $casts = [
        'party_size' => 'integer',
        'duration_minutes' => 'integer',
        'scheduled_for' => 'datetime',
        'confirmed_at' => 'datetime',
        'seated_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'no_show_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'table_id',
        'seated_comanda_id',
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

    public function seatedComanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class, 'seated_comanda_id');
    }
}
