<?php

namespace App\Models\Event;

use App\Models\BaseModel;
use App\Models\Stock\StockBalance;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends BaseModel
{
    protected $table = 'ticket_types';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'event_session_id',
        'name',
        'description',
        'price',
        'image_path',
        'image_data',
        'image_mime',
        'image_updated_at',
        'quantity_available',
        'min_per_order',
        'max_per_order',
        'sales_start_at',
        'sales_end_at',
        'status',
        'sku',
        'barcode',
        'brand',
        'unit',
        'is_lot_controlled',
        'is_expiry_controlled',
        'is_serial_controlled',
        'min_stock',
        'max_stock',
        'reorder_point',
        'reorder_qty',
        'last_purchase_cost',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'image_updated_at' => 'datetime',
        'quantity_available' => 'integer',
        'min_per_order' => 'integer',
        'max_per_order' => 'integer',
        'sales_start_at' => 'datetime',
        'sales_end_at' => 'datetime',
        'is_lot_controlled' => 'boolean',
        'is_expiry_controlled' => 'boolean',
        'is_serial_controlled' => 'boolean',
        'min_stock' => 'decimal:3',
        'max_stock' => 'decimal:3',
        'reorder_point' => 'decimal:3',
        'reorder_qty' => 'decimal:3',
        'last_purchase_cost' => 'decimal:2',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'event_id',
        'event_session_id',
        'image_data',
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

    public function batches(): HasMany
    {
        return $this->hasMany(TicketBatch::class);
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class, 'ticket_type_id');
    }
}
