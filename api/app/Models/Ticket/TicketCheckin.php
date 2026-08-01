<?php

namespace App\Models\Ticket;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de controle de acesso/portaria (spec 5.16). Uma linha por
 * TENTATIVA de check-in com o ticket encontrado (válida ou recusada) — ver
 * App\Services\Ticket\CheckinService::checkin().
 */
class TicketCheckin extends BaseModel
{
    protected $table = 'ticket_checkins';

    protected $fillable = [
        'tenant_id',
        'ticket_id',
        'gate_name',
        'operator_id',
        'checked_in_at',
        'result',
        'device_info',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'ticket_id',
        'operator_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
