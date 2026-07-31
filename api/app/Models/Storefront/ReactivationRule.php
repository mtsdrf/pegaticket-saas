<?php

namespace App\Models\Storefront;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;

class ReactivationRule extends BaseModel
{
    protected $table = 'reactivation_rules';

    protected $fillable = [
        'tenant_id',
        'days_without_order',
        'coupon_type',
        'coupon_value',
        'coupon_validity_days',
        'is_active',
    ];

    protected $casts = [
        'days_without_order' => 'integer',
        'coupon_value' => 'decimal:2',
        'coupon_validity_days' => 'integer',
        'is_active' => 'boolean',
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
