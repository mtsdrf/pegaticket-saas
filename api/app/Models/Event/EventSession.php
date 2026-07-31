<?php

namespace App\Models\Event;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSession extends BaseModel
{
    protected $table = 'event_sessions';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'name',
        'starts_at',
        'ends_at',
        'gate_opens_at',
        'capacity',
        'status',
        'sales_start_at',
        'sales_end_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'gate_opens_at' => 'datetime',
        'capacity' => 'integer',
        'sales_start_at' => 'datetime',
        'sales_end_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'event_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }
}
