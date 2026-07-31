<?php

namespace App\Models\Storefront;

use App\Models\Client\Client;
use App\Models\Tenant\Tenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log de disparo da régua de reativação — sem BaseModel de propósito (mesmo
 * desvio de CouponRedemption/FinalCustomerTenantLink): gerado só pelo
 * comando agendado, nunca editado por staff.
 */
class ReactivationDispatch extends Model
{
    use HasUuid;

    protected $table = 'reactivation_dispatches';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'client_id',
        'coupon_id',
        'dispatched_at',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
