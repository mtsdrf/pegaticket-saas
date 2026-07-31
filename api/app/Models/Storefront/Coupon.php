<?php

namespace App\Models\Storefront;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;

class Coupon extends BaseModel
{
    protected $table = 'coupons';

    protected $fillable = [
        'tenant_id',
        'code',
        'type',
        'value',
        'minimum_order_value',
        'max_uses_total',
        'max_uses_per_customer',
        'starts_at',
        'expires_at',
        'is_active',
        'allowed_payment_methods',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'minimum_order_value' => 'decimal:2',
        'max_uses_total' => 'integer',
        'max_uses_per_customer' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'allowed_payment_methods' => 'array',
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
