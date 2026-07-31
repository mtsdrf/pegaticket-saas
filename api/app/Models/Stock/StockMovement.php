<?php

namespace App\Models\Stock;

use App\Models\BaseModel;
use App\Models\Event\TicketType;
use App\Models\Tenant\Tenant;

/**
 * Ledger append-only: sem update/delete pela aplicação, só criação.
 *
 * balance_before/balance_after sempre se referem ao campo relevante para
 * o `type` daquela movimentação específica:
 * - on_hand: entry, exit, adjustment_positive, adjustment_negative, transfer, return, loss
 * - reserved: reserve, reserve_cancel
 * - blocked: block, unblock
 *
 * unit_cost: só preenchido em `type=entry` (CMV real daqui pra frente,
 * roadmap A3.13) — nullable, entrada sem custo informado fica null e é
 * ignorada no cálculo de custo médio ponderado (nunca tratada como zero).
 */
class StockMovement extends BaseModel
{
    protected $table = 'stock_movements';

    protected $fillable = [
        'tenant_id',
        'ticket_type_id',
        'location_id',
        'destination_location_id',
        'type',
        'quantity',
        'unit_cost',
        'balance_before',
        'balance_after',
        'reason',
        'notes',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'balance_before' => 'decimal:3',
        'balance_after' => 'decimal:3',
        'source_id' => 'integer',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function ticketType()
    {
        return $this->belongsTo(TicketType::class);
    }

    public function location()
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }

    public function destinationLocation()
    {
        return $this->belongsTo(StockLocation::class, 'destination_location_id');
    }
}
