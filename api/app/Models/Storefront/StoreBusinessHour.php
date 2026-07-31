<?php

namespace App\Models\Storefront;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;

class StoreBusinessHour extends BaseModel
{
    protected $table = 'store_business_hours';

    protected $fillable = [
        'tenant_id',
        'day_of_week',
        'opens_at',
        'closes_at',
        'is_closed',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_closed' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
