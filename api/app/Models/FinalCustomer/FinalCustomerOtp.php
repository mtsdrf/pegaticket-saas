<?php

namespace App\Models\FinalCustomer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Código de login sem senha (OTP), tabela auxiliar efêmera — ver migration.
 * Sem `updated_at` (só `created_at`, preenchido automaticamente pelo
 * Eloquent com UPDATED_AT desabilitado).
 */
class FinalCustomerOtp extends Model
{
    const UPDATED_AT = null;

    protected $table = 'final_customer_otps';

    protected $fillable = [
        'final_customer_id',
        'code_hash',
        'expires_at',
        'consumed_at',
        'attempts',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function finalCustomer(): BelongsTo
    {
        return $this->belongsTo(FinalCustomer::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
