<?php

namespace App\Models\Storefront;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Models\Tenant\Tenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Log de resgate de cashback — sem BaseModel de propósito (mesmo desvio
 * deliberado de CouponRedemption): sempre criado pelo sistema no checkout,
 * nunca editado por staff, sem soft delete/created_by. 1 linha por lote de
 * CashbackEarning consumido. Ver migration create_cashback_redemptions_table.
 */
class CashbackRedemption extends Model
{
    use HasUuid;

    protected $table = 'cashback_redemptions';

    protected $fillable = [
        'tenant_id',
        'final_customer_id',
        'order_id',
        'cashback_earning_id',
        'amount',
        'redeemed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'redeemed_at' => 'datetime',
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
        return $this->belongsTo(Sale::class, 'order_id');
    }

    public function cashbackEarning()
    {
        return $this->belongsTo(CashbackEarning::class);
    }
}
