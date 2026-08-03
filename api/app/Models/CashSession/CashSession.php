<?php

namespace App\Models\CashSession;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashSession extends BaseModel
{
    protected $table = 'cash_sessions';

    public const STATUS_OPEN = 'aberto';

    public const STATUS_CLOSED = 'fechado';

    protected $fillable = [
        'tenant_id',
        'opened_by',
        'closed_by',
        'opening_amount',
        'closing_amount',
        'expected_cash_amount',
        'difference_amount',
        'status',
        'opening_notes',
        'closing_notes',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'opening_amount' => 'decimal:2',
        'closing_amount' => 'decimal:2',
        'expected_cash_amount' => 'decimal:2',
        'difference_amount' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
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

    public function openedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
