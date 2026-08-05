<?php

namespace App\Models\Event;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cota de inventário por canal de venda (opt-in) — ver migration
 * create_ticket_type_channel_quotas_table e
 * App\Services\Event\TicketTypeChannelQuotaService.
 */
class TicketTypeChannelQuota extends BaseModel
{
    protected $table = 'ticket_type_channel_quotas';

    protected $fillable = [
        'tenant_id',
        'ticket_type_id',
        'channel',
        'quota',
    ];

    protected $casts = [
        'quota' => 'integer',
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
