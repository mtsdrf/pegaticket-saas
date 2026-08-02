<?php

namespace App\Models\Event;

use App\Models\BaseModel;
use App\Models\Sale\SaleItem;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketBatch extends BaseModel
{
    protected $table = 'ticket_batches';

    protected $fillable = [
        'tenant_id',
        'ticket_type_id',
        'name',
        'price',
        'quantity',
        'quantity_sold',
        'starts_at',
        'ends_at',
        'priority',
        'auto_advance',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'quantity_sold' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'priority' => 'integer',
        'auto_advance' => 'boolean',
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

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
