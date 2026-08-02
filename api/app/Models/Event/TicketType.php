<?php

namespace App\Models\Event;

use App\Models\BaseModel;
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
        'reentry_enabled',
        'max_reentries',
        'reentry_cooldown_minutes',
        'sku',
        'barcode',
        'brand',
        'unit',
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
        'reentry_enabled' => 'boolean',
        'max_reentries' => 'integer',
        'reentry_cooldown_minutes' => 'integer',
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
}
