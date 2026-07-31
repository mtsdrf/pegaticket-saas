<?php

namespace App\Models\Balcao;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Table extends BaseModel
{
    public const STATUS_FREE = 'free';
    public const STATUS_OCCUPIED = 'occupied';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_CLOSING = 'closing';

    public const STATUSES = [
        self::STATUS_FREE,
        self::STATUS_OCCUPIED,
        self::STATUS_RESERVED,
        self::STATUS_CLOSING,
    ];

    protected $table = 'tables';

    protected $fillable = [
        'tenant_id',
        'label',
        'area',
        'seats',
        'status',
    ];

    protected $casts = [
        'seats' => 'integer',
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

    public function comandas(): HasMany
    {
        return $this->hasMany(Comanda::class);
    }
}
