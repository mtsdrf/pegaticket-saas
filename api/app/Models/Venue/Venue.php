<?php

namespace App\Models\Venue;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Venue extends BaseModel
{
    protected $table = 'venues';

    protected $fillable = [
        'tenant_id',
        'name',
        'background_image_path',
        'background_image_data',
        'background_image_mime',
        'width',
        'height',
        'is_active',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'background_image_data',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function mapVersions(): HasMany
    {
        return $this->hasMany(VenueMapVersion::class);
    }

    public function publishedMapVersion(): HasOne
    {
        return $this->hasOne(VenueMapVersion::class)
            ->where('is_published', true)
            ->latestOfMany('version_number');
    }
}
