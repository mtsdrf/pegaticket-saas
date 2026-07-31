<?php

namespace App\Models\Venue;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seat extends BaseModel
{
    protected $table = 'seats';

    protected $fillable = [
        'tenant_id',
        'venue_map_version_id',
        'sector_name',
        'label',
        'kind',
        'capacity',
        'pos_x',
        'pos_y',
        'is_accessible',
        'status',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'pos_x' => 'decimal:2',
        'pos_y' => 'decimal:2',
        'is_accessible' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'venue_map_version_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function mapVersion(): BelongsTo
    {
        return $this->belongsTo(VenueMapVersion::class, 'venue_map_version_id');
    }
}
