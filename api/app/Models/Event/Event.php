<?php

namespace App\Models\Event;

use App\Models\BaseModel;
use App\Models\Finance\Receivable;
use App\Models\Tenant\Tenant;
use App\Models\Venue\VenueMapVersion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends BaseModel
{
    protected $table = 'events';

    protected $fillable = [
        'tenant_id',
        'event_category_id',
        'venue_map_version_id',
        'name',
        'slug',
        'description_short',
        'description_full',
        'cover_image_path',
        'cover_image_data',
        'cover_image_mime',
        'cover_image_updated_at',
        'type',
        'location_name',
        'location_address',
        'location_lat',
        'location_lng',
        'starts_at',
        'ends_at',
        'visibility',
        'status',
        'reentry_enabled',
        'max_reentries',
        'reentry_cooldown_minutes',
        'high_demand_mode',
        'virtual_queue_admission_batch_size',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cover_image_updated_at' => 'datetime',
        'reentry_enabled' => 'boolean',
        'max_reentries' => 'integer',
        'reentry_cooldown_minutes' => 'integer',
        'high_demand_mode' => 'boolean',
        'virtual_queue_admission_batch_size' => 'integer',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'event_category_id',
        'venue_map_version_id',
        'cover_image_data',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'event_category_id');
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    public function eventProducts(): HasMany
    {
        return $this->hasMany(EventProduct::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class);
    }

    public function venueMapVersion(): BelongsTo
    {
        return $this->belongsTo(VenueMapVersion::class);
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }
}
