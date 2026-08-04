<?php

namespace App\Models\Storefront;

use App\Models\BaseModel;
use App\Models\Event\Event;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VirtualQueueEntry extends BaseModel
{
    public const STATUS_WAITING = 'waiting';

    public const STATUS_ADMITTED = 'admitted';

    public const STATUS_EXPIRED = 'expired';

    protected $table = 'virtual_queue_entries';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'final_customer_id',
        'session_token',
        'position',
        'status',
        'admitted_at',
    ];

    protected $casts = [
        'position' => 'integer',
        'admitted_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'event_id',
        'final_customer_id',
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

    public function finalCustomer(): BelongsTo
    {
        return $this->belongsTo(FinalCustomer::class);
    }
}
