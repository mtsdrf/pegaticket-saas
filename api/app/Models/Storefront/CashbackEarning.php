<?php

namespace App\Models\Storefront;

use App\Models\BaseModel;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Order\Order;
use App\Models\Tenant\Tenant;

class CashbackEarning extends BaseModel
{
    protected $table = 'cashback_earnings';

    protected $fillable = [
        'tenant_id',
        'final_customer_id',
        'order_id',
        'amount',
        'remaining_amount',
        'status',
        'available_at',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'available_at' => 'datetime',
        'expires_at' => 'datetime',
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

    public function finalCustomer()
    {
        return $this->belongsTo(FinalCustomer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function redemptions()
    {
        return $this->hasMany(CashbackRedemption::class);
    }
}
