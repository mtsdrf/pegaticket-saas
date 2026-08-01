<?php

namespace App\Models\Storefront;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Models\Tenant\Tenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Log de uso de cupom — sem BaseModel de propósito (mesmo desvio deliberado
 * de FinalCustomerTenantLink): sempre criado pelo sistema no checkout,
 * nunca editado por staff, sem soft delete/created_by. Ver migration
 * create_coupon_redemptions_table.
 */
class CouponRedemption extends Model
{
    use HasUuid;

    protected $table = 'coupon_redemptions';

    protected $fillable = [
        'tenant_id',
        'coupon_id',
        'final_customer_id',
        'order_id',
        'redeemed_at',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function finalCustomer()
    {
        return $this->belongsTo(FinalCustomer::class);
    }

    public function order()
    {
        return $this->belongsTo(Sale::class);
    }
}
