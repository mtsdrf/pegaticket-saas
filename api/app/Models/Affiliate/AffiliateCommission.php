<?php

namespace App\Models\Affiliate;

use App\Models\BaseModel;
use App\Models\Sale\Sale;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommission extends BaseModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    protected $table = 'affiliate_commissions';

    protected $fillable = [
        'tenant_id',
        'affiliate_id',
        'sale_id',
        'sale_amount',
        'percentage_applied',
        'amount',
        'status',
    ];

    protected $casts = [
        'sale_amount' => 'decimal:2',
        'percentage_applied' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
