<?php

namespace App\Models\Event;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventProduct extends BaseModel
{
    protected $table = 'event_products';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'name',
        'description',
        'price',
        'quantity_available',
        'max_per_order',
        'sales_start_at',
        'sales_end_at',
        'kind',
        'requires_plate',
        'requires_model',
        'requires_color',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity_available' => 'integer',
        'max_per_order' => 'integer',
        'sales_start_at' => 'datetime',
        'sales_end_at' => 'datetime',
        'requires_plate' => 'boolean',
        'requires_model' => 'boolean',
        'requires_color' => 'boolean',
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
}
