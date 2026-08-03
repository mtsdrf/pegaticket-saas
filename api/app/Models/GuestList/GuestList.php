<?php

namespace App\Models\GuestList;

use App\Models\BaseModel;
use App\Models\Event\Event;
use App\Models\Event\EventSession;
use App\Models\Event\TicketType;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestList extends BaseModel
{
    protected $table = 'guest_lists';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'event_session_id',
        'ticket_type_id',
        'name',
        'quantity_per_entry',
        'notes',
    ];

    protected $casts = [
        'quantity_per_entry' => 'integer',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'event_id',
        'event_session_id',
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(GuestListEntry::class);
    }
}
