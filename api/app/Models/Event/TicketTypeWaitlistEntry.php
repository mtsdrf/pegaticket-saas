<?php

namespace App\Models\Event;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketTypeWaitlistEntry extends BaseModel
{
    protected $table = 'ticket_type_waitlist_entries';

    protected $fillable = [
        'tenant_id',
        'ticket_type_id',
        'name',
        'email',
        'quantity_desired',
        'notified_at',
    ];

    protected $casts = [
        'quantity_desired' => 'integer',
        'notified_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'ticket_type_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }
}
