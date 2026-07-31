<?php

namespace App\Models\Pdv;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends BaseModel
{
    public const TYPE_SUPPLY = 'supply';
    public const TYPE_WITHDRAWAL = 'withdrawal';

    protected $table = 'cash_movements';

    protected $fillable = [
        'tenant_id',
        'cash_session_id',
        'type',
        'amount',
        'reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'cash_session_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }
}
