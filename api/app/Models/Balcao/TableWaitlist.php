<?php

namespace App\Models\Balcao;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableWaitlist extends BaseModel
{
    public const STATUS_WAITING = 'waiting';
    public const STATUS_CALLED = 'called';
    public const STATUS_SEATED = 'seated';
    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_CALLED,
    ];

    public const TERMINAL_STATUSES = [
        self::STATUS_SEATED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'table_waitlists';

    protected $fillable = [
        'tenant_id',
        'table_id',
        'seated_comanda_id',
        'customer_name',
        'customer_phone',
        'party_size',
        'quoted_wait_minutes',
        'status',
        'notes',
        'cancelled_reason',
        'called_at',
        'called_by',
        'seated_at',
        'seated_by',
        'cancelled_at',
        'cancelled_by',
    ];

    protected $casts = [
        'party_size' => 'integer',
        'quoted_wait_minutes' => 'integer',
        'called_at' => 'datetime',
        'seated_at' => 'datetime',
        'cancelled_at' => 'datetime',
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
