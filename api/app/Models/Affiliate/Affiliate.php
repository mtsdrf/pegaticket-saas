<?php

namespace App\Models\Affiliate;

use App\Models\BaseModel;
use App\Models\Sale\Sale;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Affiliate extends BaseModel
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'affiliates';

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'tracking_code',
        'commission_percentage',
        'status',
    ];

    protected $casts = [
        'commission_percentage' => 'decimal:2',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }
}
