<?php

namespace App\Models\Sale;

use App\Models\Tenant\Tenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Link temporário de preparo (roadmap Loja) — sem BaseModel de propósito
 * (mesmo desvio de CouponRedemption): sempre criado pelo sistema, nunca
 * editado por staff, sem soft delete/created_by. Ver migration.
 */
class SalePrepLink extends Model
{
    use HasUuid;

    protected $table = 'order_prep_links';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
