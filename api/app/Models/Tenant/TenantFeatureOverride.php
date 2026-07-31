<?php

namespace App\Models\Tenant;

use App\Models\BaseModel;
use App\Models\Functionality\Functionality;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantFeatureOverride extends BaseModel
{
    protected $table = 'tenant_feature_overrides';

    protected $fillable = [
        'tenant_id',
        'functionality_id',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function functionality(): BelongsTo
    {
        return $this->belongsTo(Functionality::class);
    }
}
