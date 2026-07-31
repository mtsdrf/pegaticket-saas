<?php

namespace App\Models\Venue;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VenueMapVersion extends BaseModel
{
    protected $table = 'venue_map_versions';

    protected $fillable = [
        'tenant_id',
        'venue_id',
        'version_number',
        'is_published',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'is_published' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'venue_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }
}
