<?php

namespace App\Models\Subscription;

use App\Models\BaseModel;
use App\Models\Finance\Receivable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends BaseModel
{
    protected $table = 'payments';

    protected $fillable = [
        'payable_type',
        'payable_id',
        'provider',
        'provider_charge_id',
        'method',
        'amount',
        'status',
        'paid_at',
        'idempotency_key',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'id',
        'payable_type',
        'payable_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * Polimórfico (roadmap 2A): uma fatura de assinatura (Invoice, cobrança
     * da PegaTicket) OU uma venda (Sale, recebimento do tenant).
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function receivable(): HasOne
    {
        return $this->hasOne(Receivable::class);
    }
}
