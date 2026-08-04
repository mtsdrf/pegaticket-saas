<?php

namespace App\Models\Event;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * "Portaria" formal e opcional de um evento (ver migration
 * create_event_gates_table). Sem nenhum ticket type em allowedTicketTypes(),
 * a portaria aceita qualquer tipo de ingresso do evento.
 */
class EventGate extends BaseModel
{
    protected $table = 'event_gates';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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

    public function allowedTicketTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            TicketType::class,
            'event_gate_ticket_types',
            'event_gate_id',
            'ticket_type_id'
        );
    }
}
