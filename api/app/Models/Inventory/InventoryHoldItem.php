<?php

namespace App\Models\Inventory;

use App\Models\BaseModel;
use App\Models\Event\EventProduct;
use App\Models\Event\TicketBatch;
use App\Models\Event\TicketType;
use App\Models\Tenant\Tenant;
use App\Models\Venue\Seat;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryHoldItem extends BaseModel
{
    protected $table = 'inventory_hold_items';

    protected $fillable = [
        'tenant_id',
        'inventory_hold_id',
        'ticket_type_id',
        'event_product_id',
        'seat_id',
        'ticket_batch_id',
        'quantity',
        'unit_price',
        'meta',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'meta' => 'array',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'inventory_hold_id',
        'ticket_type_id',
        'event_product_id',
        'seat_id',
        'ticket_batch_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function hold(): BelongsTo
    {
        return $this->belongsTo(InventoryHold::class, 'inventory_hold_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function eventProduct(): BelongsTo
    {
        return $this->belongsTo(EventProduct::class);
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    public function ticketBatch(): BelongsTo
    {
        return $this->belongsTo(TicketBatch::class);
    }
}
